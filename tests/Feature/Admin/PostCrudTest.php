<?php

use App\Enums\PostStatus;
use App\Events\PostPublished;
use App\Jobs\OptimizeCoverImage;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Queue;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| Negative cases first - these are the ones that matter.
|--------------------------------------------------------------------------
*/

it('redirects a guest away from the admin area', function () {
    $this->get(route('admin.dashboard'))->assertRedirect(route('login'));
    $this->get(route('admin.posts.index'))->assertRedirect(route('login'));
});

it('does not let a guest create a post', function () {
    $this->post(route('admin.posts.store'), ['title' => 'Sneaky'])
        ->assertRedirect(route('login'));

    expect(Post::count())->toBe(0);
});

it('does not let a guest delete a post', function () {
    $post = Post::factory()->published()->create();

    $this->delete(route('admin.posts.destroy', $post))->assertRedirect(route('login'));

    // The post must still be there, not even soft deleted.
    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
});

it('does not let an author delete someone else s post', function () {
    $post = Post::factory()->published()->create();   // owned by another user
    $intruder = author();

    $this->actingAs($intruder)
        ->delete(route('admin.posts.destroy', $post))
        ->assertForbidden();

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
});

it('does not let an author edit someone else s post', function () {
    $post = Post::factory()->published()->create();

    $this->actingAs(author())
        ->get(route('admin.posts.edit', $post))
        ->assertForbidden();
});

it('lets an admin delete any post', function () {
    $post = Post::factory()->published()->create();

    $this->actingAs(admin())
        ->delete(route('admin.posts.destroy', $post))
        ->assertRedirect(route('admin.posts.index'));

    // Soft delete: the row survives so it can be restored.
    $this->assertSoftDeleted('posts', ['id' => $post->id]);
});

it('shows an author only their own posts in the list', function () {
    $author = author();
    Post::factory()->published()->for($author)->create(['title' => 'My own article']);
    Post::factory()->published()->create(['title' => 'Someone else s article']);

    $this->actingAs($author)
        ->get(route('admin.posts.index'))
        ->assertOk()
        ->assertSee('My own article')
        ->assertDontSee('Someone else s article');
});

it('shows an admin every post', function () {
    Post::factory()->published()->create(['title' => 'First article']);
    Post::factory()->draft()->create(['title' => 'Second article']);

    $this->actingAs(admin())
        ->get(route('admin.posts.index'))
        ->assertOk()
        ->assertSee('First article')
        ->assertSee('Second article');
});

/*
|--------------------------------------------------------------------------
| The happy path
|--------------------------------------------------------------------------
*/

it('creates a post', function () {
    $user = admin();
    $category = Category::factory()->create();
    $tags = Tag::factory()->count(2)->create();

    $response = $this->actingAs($user)->post(route('admin.posts.store'), [
        'title' => 'Writing a custom middleware',
        'excerpt' => 'When a policy is the wrong tool.',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'category_id' => $category->id,
        'tags' => $tags->pluck('id')->all(),
        'status' => PostStatus::Draft->value,
    ]);

    $post = Post::firstWhere('title', 'Writing a custom middleware');

    expect($post)->not->toBeNull();
    $response->assertRedirect(route('admin.posts.edit', $post));

    expect($post->user_id)->toBe($user->id)
        ->and($post->category_id)->toBe($category->id)
        ->and($post->tags)->toHaveCount(2)
        ->and($post->status)->toBe(PostStatus::Draft)
        ->and($post->slug)->toBeSlug();
});

it('generates a slug from the title when none is given', function () {
    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Route Model Binding Explained',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Draft->value,
    ]);

    $this->assertDatabaseHas('posts', ['slug' => 'route-model-binding-explained']);
});

it('makes a duplicate slug unique instead of failing', function () {
    Post::factory()->create(['title' => 'Same Title', 'slug' => 'same-title']);

    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Same Title',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Draft->value,
    ]);

    $this->assertDatabaseHas('posts', ['slug' => 'same-title-2']);
});

it('updates a post', function () {
    $post = Post::factory()->draft()->create(['title' => 'Old title']);

    $this->actingAs(admin())->put(route('admin.posts.update', $post), [
        'title' => 'New title',
        'body' => 'An updated body that is comfortably over the minimum length.',
        'status' => PostStatus::Draft->value,
    ])->assertRedirect();

    expect($post->fresh()->title)->toBe('New title');
});

it('keeps the existing slug when a post is updated', function () {
    $post = Post::factory()->draft()->create(['title' => 'Original', 'slug' => 'original']);

    $this->actingAs(admin())->put(route('admin.posts.update', $post), [
        'title' => 'Completely different title',
        'body' => 'An updated body that is comfortably over the minimum length.',
        'status' => PostStatus::Draft->value,
        'slug' => 'original',
    ]);

    // why this matters: changing a slug silently would break every existing
    // link to the article.
    expect($post->fresh()->slug)->toBe('original');
});

it('restores a trashed post', function () {
    $post = Post::factory()->published()->create();
    $post->delete();

    $this->actingAs(admin())
        ->patch(route('admin.posts.restore', $post->id))
        ->assertRedirect(route('admin.posts.index'));

    $this->assertDatabaseHas('posts', ['id' => $post->id, 'deleted_at' => null]);
});

/*
|--------------------------------------------------------------------------
| Validation
|--------------------------------------------------------------------------
*/

it('validates required fields when creating a post', function () {
    $this->actingAs(admin())
        ->post(route('admin.posts.store'), [])
        ->assertSessionHasErrors(['title', 'body', 'status']);

    expect(Post::count())->toBe(0);
});

it('requires a date for a scheduled post', function () {
    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Scheduled without a date',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Scheduled->value,
    ])->assertSessionHasErrors('published_at');
});

it('rejects a status that is not a real status', function () {
    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Bad status',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => 'wizard',
    ])->assertSessionHasErrors('status');
});

it('sets published_at automatically when publishing without a date', function () {
    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Publish right now',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Published->value,
    ]);

    $post = Post::firstWhere('title', 'Publish right now');

    expect($post->published_at)->not->toBeNull()
        ->and($post->isPublished())->toBeTrue();
});

/*
|--------------------------------------------------------------------------
| Uploads, events and jobs
|--------------------------------------------------------------------------
*/

it('stores a cover image and queues it for resizing', function () {
    Storage::fake('public');
    Queue::fake();

    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Post with a cover',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('cover.jpg', 1200, 800),
    ])->assertRedirect();

    $post = Post::firstWhere('title', 'Post with a cover');

    expect($post->cover_image)->not->toBeNull();
    Storage::disk('public')->assertExists($post->cover_image);

    // The heavy work happens off the request.
    Queue::assertPushed(OptimizeCoverImage::class);
});

it('rejects a cover image that is not an image', function () {
    Storage::fake('public');

    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Bad upload',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Draft->value,
        // A PHP file renamed to .jpg must not get through - the `image` and
        // `mimes` rules check the real content, not the extension.
        'cover_image' => UploadedFile::fake()->create('payload.jpg', 100, 'application/x-php'),
    ])->assertSessionHasErrors('cover_image');
});

it('rejects an oversized cover image', function () {
    Storage::fake('public');

    $this->actingAs(admin())->post(route('admin.posts.store'), [
        'title' => 'Huge upload',
        'body' => 'A body long enough to satisfy the minimum length rule for posts.',
        'status' => PostStatus::Draft->value,
        'cover_image' => UploadedFile::fake()->image('huge.jpg', 2000, 1500)->size(5000),
    ])->assertSessionHasErrors('cover_image');
});

it('dispatches PostPublished when a draft becomes published', function () {
    Event::fake([PostPublished::class]);

    $post = Post::factory()->draft()->create();

    $this->actingAs(admin())->put(route('admin.posts.update', $post), [
        'title' => $post->title,
        'body' => $post->body,
        'status' => PostStatus::Published->value,
    ]);

    Event::assertDispatched(PostPublished::class);
});

it('does not dispatch PostPublished when editing an already published post', function () {
    Event::fake([PostPublished::class]);

    $post = Post::factory()->published()->create();

    $this->actingAs(admin())->put(route('admin.posts.update', $post), [
        'title' => 'A small typo fix',
        'body' => $post->body,
        'status' => PostStatus::Published->value,
        'published_at' => $post->published_at->format('Y-m-d\TH:i'),
    ]);

    Event::assertNotDispatched(PostPublished::class);
});

it('writes an activity log entry when a post is published', function () {
    $post = Post::factory()->draft()->create();

    $this->actingAs(admin())->put(route('admin.posts.update', $post), [
        'title' => $post->title,
        'body' => $post->body,
        'status' => PostStatus::Published->value,
    ]);

    // The polymorphic audit trail.
    $this->assertDatabaseHas('activity_logs', [
        'action' => 'post.published',
        'subject_type' => Post::class,
        'subject_id' => $post->id,
    ]);
});

/*
|--------------------------------------------------------------------------
| Draft preview
|--------------------------------------------------------------------------
*/

it('lets an author preview their own draft', function () {
    $author = author();
    $post = Post::factory()->draft()->for($author)->create(['title' => 'Unfinished thoughts']);

    $this->actingAs($author)
        ->get(route('admin.posts.preview', $post))
        ->assertOk()
        ->assertSee('Unfinished thoughts')
        ->assertSee(__('blog.draft_preview'));
});

it('does not let another author preview a draft', function () {
    $post = Post::factory()->draft()->create();

    $this->actingAs(author())
        ->get(route('admin.posts.preview', $post))
        ->assertForbidden();
});

it('does not let a guest preview a draft', function () {
    $post = Post::factory()->draft()->create();

    $this->get(route('admin.posts.preview', $post))->assertRedirect(route('login'));
});
