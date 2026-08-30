<?php

use App\Enums\PostStatus;
use App\Models\ActivityLog;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Support\Carbon;

it('links a post to its author and category', function () {
    $post = Post::factory()->published()->create();

    expect($post->user)->toBeInstanceOf(User::class)
        ->and($post->category)->toBeInstanceOf(Category::class);
});

it('links a post to many tags and back again', function () {
    $post = Post::factory()->published()->create();
    $tags = Tag::factory()->count(3)->create();

    $post->tags()->sync($tags->pluck('id'));

    expect($post->fresh()->tags)->toHaveCount(3)
        ->and($tags->first()->fresh()->posts)->toHaveCount(1);
});

it('cannot attach the same tag twice', function () {
    $post = Post::factory()->published()->create();
    $tag = Tag::factory()->create();

    $post->tags()->sync([$tag->id, $tag->id]);

    // The composite primary key on the pivot makes duplicates impossible.
    expect($post->fresh()->tags)->toHaveCount(1);
});

it('threads replies through the self referencing relation', function () {
    $parent = Comment::factory()->approved()->create();
    $reply = Comment::factory()->approved()->replyTo($parent)->create();

    expect($reply->parent->id)->toBe($parent->id)
        ->and($parent->replies)->toHaveCount(1)
        ->and($parent->replies->first()->id)->toBe($reply->id);
});

it('reaches comments on an author s posts through hasManyThrough', function () {
    $author = author();
    $post = Post::factory()->published()->for($author)->create();
    Comment::factory()->count(3)->approved()->for($post)->create();

    // No user_id on comments - the link runs through posts.
    expect($author->commentsOnPosts)->toHaveCount(3);
});

it('records activity polymorphically against different models', function () {
    $post = Post::factory()->published()->create();
    $comment = Comment::factory()->approved()->create();

    ActivityLog::record('post.published', $post);
    ActivityLog::record('comment.approved', $comment);

    expect($post->activityLogs)->toHaveCount(1)
        ->and($comment->activityLogs)->toHaveCount(1)
        ->and($post->activityLogs->first()->subject)->toBeInstanceOf(Post::class)
        ->and($comment->activityLogs->first()->subject)->toBeInstanceOf(Comment::class);
});

it('deletes comments when their post is force deleted', function () {
    $post = Post::factory()->published()->create();
    $comment = Comment::factory()->approved()->for($post)->create();

    $post->forceDelete();

    // cascadeOnDelete on the comments FK.
    $this->assertDatabaseMissing('comments', ['id' => $comment->id]);
});

it('keeps comments when a post is only soft deleted', function () {
    $post = Post::factory()->published()->create();
    $comment = Comment::factory()->approved()->for($post)->create();

    $post->delete();

    // A soft delete is just a timestamp, so nothing cascades - which is what
    // makes restoring a post give you the whole conversation back.
    $this->assertDatabaseHas('comments', ['id' => $comment->id]);
});

it('hides soft deleted posts from queries but keeps the row', function () {
    $post = Post::factory()->published()->create();
    $post->delete();

    expect(Post::count())->toBe(0)
        ->and(Post::withTrashed()->count())->toBe(1)
        ->and(Post::onlyTrashed()->count())->toBe(1);

    $post->restore();

    expect(Post::count())->toBe(1);
});

it('casts status to an enum and published_at to a date', function () {
    $post = Post::factory()->published()->create();

    expect($post->status)->toBeInstanceOf(PostStatus::class)
        ->and($post->published_at)->toBeInstanceOf(Carbon::class)
        ->and($post->view_count)->toBeInt();
});

it('only treats a post as published when the date has passed', function () {
    $future = Post::factory()->create([
        'status' => PostStatus::Published,
        'published_at' => now()->addDay(),
    ]);

    $past = Post::factory()->published()->create(['published_at' => now()->subDay()]);

    expect($future->isPublished())->toBeFalse()
        ->and($past->isPublished())->toBeTrue();

    // And the scope agrees with the model method.
    expect(Post::published()->pluck('id')->all())->toBe([$past->id]);
});

it('falls back to the body when a post has no excerpt', function () {
    $post = Post::factory()->create([
        'excerpt' => null,
        'body' => 'The opening sentence of the article body goes right here.',
    ]);

    expect($post->summary)->toContain('The opening sentence');
});

it('prefers the excerpt when one is written', function () {
    $post = Post::factory()->create([
        'excerpt' => 'A hand written summary.',
        'body' => 'Something else entirely in the body.',
    ]);

    expect($post->summary)->toBe('A hand written summary.');
});

it('builds initials from a comment author name', function () {
    $comment = Comment::factory()->make(['author_name' => 'Ada Lovelace']);

    expect($comment->initials)->toBe('AL');
});

it('generates a url friendly slug automatically', function () {
    $post = Post::factory()->create(['title' => 'Hello, World! A Post?', 'slug' => null]);

    expect($post->slug)->toBeSlug()->toBe('hello-world-a-post');
});
