<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;

it('shows published posts on the home page', function () {
    $post = Post::factory()->published()->create(['title' => 'A published article']);

    $this->get(route('home'))
        ->assertOk()
        ->assertSee('A published article');
});

it('hides drafts and scheduled posts from the home page', function () {
    Post::factory()->draft()->create(['title' => 'Secret draft']);
    Post::factory()->scheduled()->create(['title' => 'Not out yet']);

    $this->get(route('home'))
        ->assertOk()
        ->assertDontSee('Secret draft')
        ->assertDontSee('Not out yet');
});

it('paginates the post index', function () {
    Post::factory()->count(12)->published()->create();

    $this->get(route('posts.index'))
        ->assertOk()
        // config('blog.per_page') is 9, so a second page must exist.
        ->assertSee('page=2');
});

it('shows a single post by its slug', function () {
    $post = Post::factory()->published()->create([
        'title' => 'Understanding route model binding',
        'body' => 'A long body about **binding** that is definitely more than twenty characters.',
    ]);

    $this->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('Understanding route model binding')
        ->assertSee('<strong>binding</strong>', escape: false);
});

it('returns 404 for a draft post', function () {
    $post = Post::factory()->draft()->create();

    $this->get(route('posts.show', $post))->assertNotFound();
});

it('returns 404 for a post scheduled in the future', function () {
    $post = Post::factory()->scheduled()->create();

    $this->get(route('posts.show', $post))->assertNotFound();
});

it('returns 404 for a soft deleted post', function () {
    $post = Post::factory()->published()->create();
    $post->delete();

    $this->get(route('posts.show', $post))->assertNotFound();
});

it('returns 404 for an unknown slug', function () {
    $this->get('/posts/no-such-article')->assertNotFound();
});

it('counts a view only once per session', function () {
    $post = Post::factory()->published()->create(['view_count' => 0]);

    $this->get(route('posts.show', $post));
    $this->get(route('posts.show', $post));
    $this->get(route('posts.show', $post));

    expect($post->fresh()->view_count)->toBe(1);
});

it('does not bump updated_at when counting a view', function () {
    $post = Post::factory()->published()->create(['view_count' => 0]);
    $before = $post->updated_at;

    $this->travelTo(now()->addHour());
    $this->get(route('posts.show', $post));

    expect($post->fresh()->updated_at->timestamp)->toBe($before->timestamp);
});

it('lists posts of a category', function () {
    $category = Category::factory()->create(['name' => 'Engineering']);
    Post::factory()->published()->for($category)->create(['title' => 'In the category']);
    Post::factory()->published()->create(['title' => 'Somewhere else']);

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertSee('In the category')
        ->assertDontSee('Somewhere else');
});

it('lists posts carrying a tag', function () {
    $tag = Tag::factory()->create(['name' => 'Docker']);
    $tagged = Post::factory()->published()->create(['title' => 'Tagged article']);
    $tagged->tags()->attach($tag);
    Post::factory()->published()->create(['title' => 'Untagged article']);

    $this->get(route('tags.show', $tag))
        ->assertOk()
        ->assertSee('Tagged article')
        ->assertDontSee('Untagged article');
});

it('shows an empty state when a category has no posts', function () {
    $category = Category::factory()->create();

    $this->get(route('categories.show', $category))
        ->assertOk()
        ->assertSee(__('blog.no_posts_in_category'));
});

it('escapes html in a post title', function () {
    // Stored XSS check: a title is printed with {{ }} so it must arrive escaped.
    Post::factory()->published()->create(['title' => 'Bad <script>alert(1)</script> title']);

    $this->get(route('posts.index'))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});

it('shows related posts from the same category', function () {
    $category = Category::factory()->create();
    $post = Post::factory()->published()->for($category)->create();
    Post::factory()->published()->for($category)->create(['title' => 'A sibling article']);

    $this->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('A sibling article');
});
