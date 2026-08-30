<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\PostPublished;
use App\Models\ActivityLog;
use App\Support\CacheKeys;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Log;

/**
 * Records a publication in the audit trail and clears the caches that now
 * show stale content.
 *
 * why NOT queued: this must be true the instant the redirect happens, or the
 * author would reload the homepage and still see the old featured post.
 * Compare with SendCommentNotification, which is queued because nobody is
 * waiting for an email to send.
 */
class LogPostPublication
{
    public function handle(PostPublished $event): void
    {
        ActivityLog::record('post.published', $event->post, $event->post->user_id);

        foreach (CacheKeys::postRelated() as $key) {
            Cache::forget($key);
        }

        Log::info('Post published', [
            'post_id' => $event->post->id,
            'slug' => $event->post->slug,
        ]);
    }
}
