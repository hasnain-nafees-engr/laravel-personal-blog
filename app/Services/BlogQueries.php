<?php

declare(strict_types=1);

namespace App\Services;

use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Support\CacheKeys;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Support\Facades\Cache;

/**
 * Read-side queries for the public site, with caching.
 *
 * CACHING RULE FOR THIS PROJECT: only arrays and scalars go into the cache,
 * never Eloquent models or collections.
 *
 * why: Laravel 13 ships `cache.serializable_classes => false`, which makes
 * every cache store unserialize with `allowed_classes: false`. A cached model
 * comes back as __PHP_Incomplete_Class - silently, with no exception - and
 * blows up only when a view touches a property. Caching plain arrays sidesteps
 * the whole problem and is faster to unserialize anyway.
 *
 * INVALIDATION: PostObserver + LogPostPublication forget every key listed in
 * CacheKeys::postRelated() whenever a post is saved, deleted or restored.
 * The database cache driver has no tag support, so keys are forgotten by name.
 */
final class BlogQueries
{
    /**
     * Categories with their published-post counts, for the sidebar.
     *
     * @return list<array{name: string, slug: string, posts_count: int}>
     */
    public function sidebarCategories(): array
    {
        return Cache::remember(
            CacheKeys::SIDEBAR_CATEGORIES,
            (int) config('blog.cache_ttl'),
            fn (): array => Category::query()
                // why the @var docblocks: withCount() and whereHas() hand the
                // closure a generic Builder, so the analyser cannot see that
                // it belongs to Post and therefore has the published() scope.
                ->withCount(['posts' => function (Builder $q): void {
                    /** @var Builder<Post> $q */
                    $q->published();
                }])
                // why whereHas and not having('posts_count', '>', 0):
                // withCount adds a SELECT subquery, and PostgreSQL does not
                // allow a select alias in HAVING (MySQL does, which is how
                // this trap gets written). whereHas emits an EXISTS clause,
                // which is correct everywhere.
                ->whereHas('posts', function (Builder $q): void {
                    /** @var Builder<Post> $q */
                    $q->published();
                })
                ->orderByDesc('posts_count')
                ->get()
                ->map(fn (Category $c): array => [
                    'name' => $c->name,
                    'slug' => $c->slug,
                    'posts_count' => (int) $c->posts_count,
                ])
                ->all(),
        );
    }

    /**
     * The most used tags, for the tag cloud.
     *
     * @return list<array{name: string, slug: string, posts_count: int}>
     */
    public function popularTags(int $limit = 12): array
    {
        return Cache::remember(
            CacheKeys::SIDEBAR_TAGS,
            (int) config('blog.cache_ttl'),
            fn (): array => Tag::query()
                ->withCount(['posts' => function (Builder $q): void {
                    /** @var Builder<Post> $q */
                    $q->published();
                }])
                ->whereHas('posts', function (Builder $q): void {
                    /** @var Builder<Post> $q */
                    $q->published();
                })
                ->orderByDesc('posts_count')
                ->limit($limit)
                ->get()
                ->map(fn (Tag $t): array => [
                    'name' => $t->name,
                    'slug' => $t->slug,
                    'posts_count' => (int) $t->posts_count,
                ])
                ->all(),
        );
    }

    /**
     * Counts for the admin dashboard.
     *
     * @return array<string, int>
     */
    public function dashboardCounts(): array
    {
        return Cache::remember(
            CacheKeys::DASHBOARD_COUNTS,
            300,
            fn (): array => [
                'published' => Post::published()->count(),
                'drafts' => Post::draft()->count(),
                'scheduled' => Post::scheduled()->count(),
                'categories' => Category::count(),
                'tags' => Tag::count(),
                'comments_pending' => Comment::pending()->count(),
                'comments_total' => Comment::count(),
                'views' => (int) Post::sum('view_count'),
            ],
        );
    }

    /**
     * Posts related to the given one: same category first, then shared tags.
     *
     * Not cached: it is per-post and cheap, and caching it would mean one key
     * per article to invalidate.
     *
     * @return Collection<int, Post>
     */
    public function relatedPosts(Post $post): Collection
    {
        $tagIds = $post->tags->pluck('id');

        return Post::query()
            ->published()
            ->whereKeyNot($post->id)
            // why both: x-post-card renders the author name and the comment
            // count as well as the category. Loading only `category` here is
            // exactly the N+1 bug that Model::preventLazyLoading() catches in
            // development - it threw until this line listed every relation
            // the card touches.
            ->with(['category', 'user'])
            ->withCount('approvedComments')
            ->where(function (Builder $query) use ($post, $tagIds): void {
                $query->where('category_id', $post->category_id)
                    ->orWhereHas('tags', fn (Builder $q) => $q->whereIn('tags.id', $tagIds));
            })
            ->latest('published_at')
            ->limit((int) config('blog.related_posts'))
            ->get();
    }
}
