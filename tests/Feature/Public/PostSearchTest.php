<?php

use App\Livewire\PostSearch;
use App\Models\Post;
use Livewire\Livewire;

it('renders the search component', function () {
    Livewire::test(PostSearch::class)
        ->assertOk()
        ->assertSee(__('blog.search_placeholder'));
});

it('shows no results before the minimum term length', function () {
    Post::factory()->published()->create(['title' => 'Docker in practice']);

    Livewire::test(PostSearch::class)
        ->set('term', 'd')          // one character - below the threshold
        ->assertSee(__('blog.search_hint'))
        ->assertDontSee('Docker in practice');
});

it('finds a post by title', function () {
    Post::factory()->published()->create(['title' => 'Docker in practice']);
    Post::factory()->published()->create(['title' => 'Something unrelated']);

    Livewire::test(PostSearch::class)
        ->set('term', 'docker')
        ->assertSee('Docker in practice')
        ->assertDontSee('Something unrelated');
});

it('searches case insensitively', function () {
    // This is what the PostgreSQL ILIKE in Post::scopeSearch buys us, and
    // why the suite runs on PostgreSQL rather than SQLite.
    Post::factory()->published()->create(['title' => 'Docker in practice']);

    Livewire::test(PostSearch::class)
        ->set('term', 'DOCKER')
        ->assertSee('Docker in practice');
});

it('searches the excerpt as well as the title', function () {
    Post::factory()->published()->create([
        'title' => 'An unrelated headline',
        'excerpt' => 'This article is really about Postgres indexes.',
    ]);

    Livewire::test(PostSearch::class)
        ->set('term', 'postgres')
        ->assertSee('An unrelated headline');
});

it('never surfaces drafts in search', function () {
    Post::factory()->draft()->create(['title' => 'Draft about Docker']);

    Livewire::test(PostSearch::class)
        ->set('term', 'docker')
        ->assertDontSee('Draft about Docker');
});

it('shows an empty state when nothing matches', function () {
    Post::factory()->published()->create(['title' => 'Docker in practice']);

    Livewire::test(PostSearch::class)
        ->set('term', 'kubernetes')
        ->assertSee('kubernetes');   // "Nothing matched "kubernetes"."
});

it('treats a percent sign as text, not a wildcard', function () {
    // why: without escaping, a search for "%" would match every post.
    Post::factory()->published()->create(['title' => 'Plain article']);

    Livewire::test(PostSearch::class)
        ->set('term', '%%')
        ->assertDontSee('Plain article');
});

it('clears the term', function () {
    Livewire::test(PostSearch::class)
        ->set('term', 'docker')
        ->call('clear')
        ->assertSet('term', '');
});

it('limits how many results are shown', function () {
    Post::factory()->count(10)->published()->create(['title' => 'Docker note']);

    $results = Livewire::test(PostSearch::class)->set('term', 'docker')->instance()->results;

    expect($results)->toHaveCount(6);
});
