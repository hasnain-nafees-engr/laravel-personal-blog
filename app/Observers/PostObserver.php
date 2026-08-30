<?php

declare(strict_types=1);

namespace App\Observers;

use App\Events\PostPublished;
use App\Models\Post;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Str;

/**
 * Model lifecycle hooks for Post.
 *
 * why an observer rather than code in the controller: these rules must hold
 * no matter who saves the post - a controller, a seeder, an Artisan command
 * or tinker. Putting them here makes that impossible to bypass.
 */
class PostObserver
{
    public function creating(Post $post): void
    {
        $post->slug = $post->slug ?: $this->uniqueSlug($post->title);
    }

    public function updating(Post $post): void
    {
        // Regenerate the slug only if an editor cleared it - changing slugs
        // on a live post would break every existing link to it.
        if (blank($post->slug)) {
            $post->slug = $this->uniqueSlug($post->title, $post->id);
        }
    }

    public function saved(Post $post): void
    {
        $this->forgetCaches();

        // Fire the domain event when a post BECOMES published, not on every
        // later save. wasChanged() compares against the values loaded from
        // the database, so an already-published post being edited is quiet.
        if ($post->isPublished() && $post->wasChanged('status')) {
            PostPublished::dispatch($post);
        }
    }

    public function deleted(Post $post): void
    {
        $this->forgetCaches();
    }

    public function restored(Post $post): void
    {
        $this->forgetCaches();
    }

    private function forgetCaches(): void
    {
        foreach (CacheKeys::postRelated() as $key) {
            Cache::forget($key);
        }
    }

    /** A slug that is unique across the posts table, suffixing -2, -3 ... */
    private function uniqueSlug(string $title, ?int $ignoreId = null): string
    {
        $base = Str::slug($title) ?: 'post';
        $slug = $base;
        $suffix = 2;

        while (
            Post::withTrashed()
                ->where('slug', $slug)
                ->when($ignoreId !== null, fn ($q) => $q->whereKeyNot($ignoreId))
                ->exists()
        ) {
            $slug = "{$base}-{$suffix}";
            $suffix++;
        }

        return $slug;
    }
}
