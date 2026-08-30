<?php

use App\Models\Category;
use App\Models\Post;
use App\Services\BlogQueries;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;

beforeEach(function () {
    $this->queries = app(BlogQueries::class);
});

it('caches the sidebar categories', function () {
    Category::factory()->create(['name' => 'Engineering']);
    Post::factory()->published()->for(Category::first())->create();

    expect(Cache::has(CacheKeys::SIDEBAR_CATEGORIES))->toBeFalse();

    $this->queries->sidebarCategories();

    expect(Cache::has(CacheKeys::SIDEBAR_CATEGORIES))->toBeTrue();
});

it('caches only plain arrays, never eloquent models', function () {
    // why this test exists: Laravel 13 sets cache.serializable_classes to
    // false, so a cached Eloquent model comes back as __PHP_Incomplete_Class
    // with no warning and explodes later in a Blade view. Caching arrays
    // sidesteps that entirely - this test locks the rule in.
    Category::factory()->create();
    Post::factory()->published()->for(Category::first())->create();

    $this->queries->sidebarCategories();
    $cached = Cache::get(CacheKeys::SIDEBAR_CATEGORIES);

    expect($cached)->toBeArray();

    foreach ($cached as $row) {
        expect($row)->toBeArray()
            ->and($row)->toHaveKeys(['name', 'slug', 'posts_count']);
    }
});

it('clears post related caches when a post is saved', function () {
    Cache::put(CacheKeys::SIDEBAR_CATEGORIES, ['stale'], 600);
    Cache::put(CacheKeys::DASHBOARD_COUNTS, ['stale'], 600);

    Post::factory()->published()->create();

    // PostObserver::saved forgets every key in CacheKeys::postRelated().
    expect(Cache::has(CacheKeys::SIDEBAR_CATEGORIES))->toBeFalse()
        ->and(Cache::has(CacheKeys::DASHBOARD_COUNTS))->toBeFalse();
});

it('clears caches when a post is deleted', function () {
    $post = Post::factory()->published()->create();
    Cache::put(CacheKeys::SIDEBAR_TAGS, ['stale'], 600);

    $post->delete();

    expect(Cache::has(CacheKeys::SIDEBAR_TAGS))->toBeFalse();
});

it('clears caches when a post is restored', function () {
    $post = Post::factory()->published()->create();
    $post->delete();
    Cache::put(CacheKeys::FEATURED_POST, 'stale', 600);

    $post->restore();

    expect(Cache::has(CacheKeys::FEATURED_POST))->toBeFalse();
});

it('reflects a new post in the sidebar counts after invalidation', function () {
    $category = Category::factory()->create(['name' => 'Engineering']);
    Post::factory()->published()->for($category)->create();

    expect($this->queries->sidebarCategories()[0]['posts_count'])->toBe(1);

    // Adding a post must not leave the sidebar showing the old number.
    Post::factory()->published()->for($category)->create();

    expect($this->queries->sidebarCategories()[0]['posts_count'])->toBe(2);
});

it('counts only published posts in the dashboard numbers', function () {
    Post::factory()->count(3)->published()->create();
    Post::factory()->count(2)->draft()->create();
    Post::factory()->scheduled()->create();

    $counts = $this->queries->dashboardCounts();

    expect($counts['published'])->toBe(3)
        ->and($counts['drafts'])->toBe(2)
        ->and($counts['scheduled'])->toBe(1);
});
