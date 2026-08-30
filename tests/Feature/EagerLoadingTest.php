<?php

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\LazyLoadingViolationException;
use Illuminate\Support\Facades\DB;

/*
|--------------------------------------------------------------------------
| The N+1 problem, demonstrated and then guarded.
|--------------------------------------------------------------------------
|
| "N+1" means: 1 query to fetch a list, then N more - one per row - because a
| relation was read lazily while rendering. It is invisible on a laptop with
| three rows of test data and crippling on a real site.
|
*/

/** Run a callback and return how many database queries it took. */
function countQueries(callable $callback): int
{
    DB::flushQueryLog();
    DB::enableQueryLog();

    $callback();

    $count = count(DB::getQueryLog());
    DB::disableQueryLog();

    return $count;
}

it('shows the N+1 problem when relations are loaded lazily', function () {
    Post::factory()->count(10)->published()->create();

    // Lazy loading is normally an exception outside production (see
    // BlogServiceProvider), so it has to be switched off to demonstrate the
    // problem at all - which is rather the point.
    Model::preventLazyLoading(false);

    $lazy = countQueries(function () {
        // BAD: no with(). Each ->user and ->category is a fresh query.
        foreach (Post::published()->get() as $post) {
            $post->user->name;
            $post->category?->name;
        }
    });

    // 1 for the posts, plus up to 2 more for every single post.
    expect($lazy)->toBeGreaterThan(10);

    Model::preventLazyLoading();
});

it('fixes the N+1 problem with eager loading', function () {
    Post::factory()->count(10)->published()->create();

    $eager = countQueries(function () {
        // GOOD: with() fetches all authors in one query and all categories in
        // another, then matches them to the posts in PHP.
        foreach (Post::published()->with(['user', 'category'])->get() as $post) {
            $post->user->name;
            $post->category?->name;
        }
    });

    // Three queries - posts, users, categories - no matter how many posts.
    expect($eager)->toBe(3);
});

it('renders the post index with a query count that does not grow with the posts', function () {
    Category::factory()->count(3)->create();
    Post::factory()->count(9)->published()->create();

    $withNine = countQueries(fn () => $this->get(route('posts.index'))->assertOk());

    Post::factory()->count(9)->published()->create();

    $withEighteen = countQueries(fn () => $this->get(route('posts.index'))->assertOk());

    expect($withEighteen)->toBeLessThanOrEqual($withNine);
});

it('renders a post page with a query count that does not grow with the comments', function () {
    $post = Post::factory()->published()->create();
    Comment::factory()->count(2)->approved()->for($post)->create();

    $withTwo = countQueries(fn () => $this->get(route('posts.show', $post))->assertOk());

    $busier = Post::factory()->published()->create();
    Comment::factory()->count(8)->approved()->for($busier)->create();

    $withEight = countQueries(fn () => $this->get(route('posts.show', $busier))->assertOk());

    expect($withEight)->toBeLessThanOrEqual($withTwo);
});

it('throws when a relation is lazy loaded outside production', function () {
    // This guard caught a real bug while this project was built:
    // BlogQueries::relatedPosts() eager-loaded `category`, but the post card
    // also renders the author's name.
    //
    // why the collection has to hold MORE THAN ONE post: Laravel arms the
    // guard in Builder::hydrate() only when `count($items) > 1`. Reading a
    // relation on a single model costs exactly one extra query - annoying,
    // but not the N+1 pattern the guard exists to catch - so the framework
    // deliberately allows it.
    Post::factory()->count(2)->published()->create();

    $posts = Post::published()->get();   // no with(), two rows

    expect(fn () => $posts->first()->user->name)
        ->toThrow(LazyLoadingViolationException::class);
});

it('allows an eager loaded relation without complaint', function () {
    Post::factory()->count(2)->published()->create();

    $posts = Post::published()->with('user')->get();

    expect($posts->first()->user->name)->toBeString();
});
