<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Laravel\Sanctum\Sanctum;

it('lists published posts as json', function () {
    Post::factory()->count(3)->published()->create();

    $this->getJson(route('api.posts.index'))
        ->assertOk()
        ->assertJsonCount(3, 'data')
        ->assertJsonStructure([
            'data' => [
                '*' => [
                    'id', 'title', 'slug', 'excerpt', 'url',
                    'published_at', 'reading_time_minutes', 'view_count',
                    'author' => ['name'],
                ],
            ],
            // Laravel's paginator adds these itself.
            'links' => ['first', 'last', 'prev', 'next'],
            'meta' => ['current_page', 'total', 'per_page'],
        ]);
});

it('never exposes drafts or scheduled posts through the api', function () {
    Post::factory()->published()->create(['title' => 'Public article']);
    Post::factory()->draft()->create(['title' => 'Private draft']);
    Post::factory()->scheduled()->create(['title' => 'Future article']);

    $response = $this->getJson(route('api.posts.index'))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonFragment(['title' => 'Public article'])
        ->assertJsonMissing(['title' => 'Private draft'])
        ->assertJsonMissing(['title' => 'Future article']);
});

it('returns a single post by slug', function () {
    $category = Category::factory()->create(['name' => 'Engineering']);
    $tag = Tag::factory()->create(['name' => 'Docker']);
    $post = Post::factory()->published()->for($category)->create(['title' => 'One article']);
    $post->tags()->attach($tag);

    $this->getJson(route('api.posts.show', $post))
        ->assertOk()
        ->assertJsonPath('data.title', 'One article')
        ->assertJsonPath('data.category.name', 'Engineering')
        ->assertJsonPath('data.tags.0.name', 'Docker')
        // The full body is only included on the single-post endpoint.
        ->assertJsonStructure(['data' => ['body_html']]);
});

it('omits the article body from the list endpoint', function () {
    Post::factory()->published()->create();

    $data = $this->getJson(route('api.posts.index'))->assertOk()->json('data.0');

    expect($data)->not->toHaveKey('body_html');
});

it('returns 404 for a draft', function () {
    $post = Post::factory()->draft()->create();

    $this->getJson(route('api.posts.show', $post))
        ->assertNotFound()
        ->assertJsonStructure(['message']);   // a JSON error, not an HTML page
});

it('can search posts through the api', function () {
    Post::factory()->published()->create(['title' => 'Docker for Laravel developers']);
    Post::factory()->published()->create(['title' => 'Something entirely different']);

    $response = $this->getJson(route('api.posts.index', ['q' => 'docker']))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonFragment(['title' => 'Docker for Laravel developers']);
});

it('can filter posts by category through the api', function () {
    $category = Category::factory()->create(['slug' => 'engineering']);
    Post::factory()->published()->for($category)->create(['title' => 'In engineering']);
    Post::factory()->published()->create(['title' => 'Elsewhere']);

    $response = $this->getJson(route('api.posts.index', ['category' => 'engineering']))->assertOk();

    expect($response->json('data'))->toHaveCount(1);
    $response->assertJsonFragment(['title' => 'In engineering']);
});

it('caps the page size so nobody can request the whole table', function () {
    Post::factory()->count(12)->published()->create();

    $this->getJson(route('api.posts.index', ['per_page' => 5000]))
        ->assertOk()
        ->assertJsonPath('meta.per_page', 50);
});

it('requires authentication for the sanctum guarded route', function () {
    $this->getJson('/api/user')->assertUnauthorized();
});

it('returns the user for an authenticated sanctum request', function () {
    $user = author();

    Sanctum::actingAs($user);

    $this->getJson('/api/user')
        ->assertOk()
        ->assertJsonPath('email', $user->email);
});
