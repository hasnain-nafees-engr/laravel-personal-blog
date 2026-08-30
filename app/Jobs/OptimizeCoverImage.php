<?php

declare(strict_types=1);

namespace App\Jobs;

use App\Models\Post;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Foundation\Queue\Queueable;
use Illuminate\Queue\Attributes\Backoff;
use Illuminate\Queue\Attributes\Tries;
use Illuminate\Support\Facades\Storage;
use Intervention\Image\Laravel\Facades\Image;

/**
 * Shrinks an uploaded cover image after the response has been sent.
 *
 * why a queued job: resizing a 4 MB photo takes real CPU time. Doing it in
 * the request would make the editor stare at a spinner after clicking Save.
 * Instead we store the original, return immediately, and this job (run by the
 * `queue` container) replaces it with a web-sized version.
 *
 * The #[Tries] / #[Backoff] attributes are Laravel 13's declarative form of
 * the classic `public int $tries = 3;` properties.
 */
#[Tries(3)]
#[Backoff(15)]
class OptimizeCoverImage implements ShouldQueue
{
    use Queueable;

    public function __construct(public readonly int $postId, public readonly string $path) {}

    public function handle(): void
    {
        $disk = Storage::disk('public');

        // The post (or the image) may have been deleted while we queued.
        if (! $disk->exists($this->path)) {
            return;
        }

        $post = Post::find($this->postId);

        if ($post === null || $post->cover_image !== $this->path) {
            return;
        }

        $maxWidth = (int) config('blog.cover_max_width', 1600);

        $image = Image::read($disk->path($this->path));

        // scaleDown never enlarges a small image - it only shrinks big ones.
        $image->scaleDown(width: $maxWidth);

        $disk->put($this->path, (string) $image->encodeByExtension(
            pathinfo($this->path, PATHINFO_EXTENSION) ?: 'jpg',
            quality: 82,
        ));
    }
}
