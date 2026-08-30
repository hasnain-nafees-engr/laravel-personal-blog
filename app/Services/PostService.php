<?php

declare(strict_types=1);

namespace App\Services;

use App\Jobs\OptimizeCoverImage;
use App\Models\Post;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * All the writing rules for a post in one place.
 *
 * why a service: creating a post is not one save - it is a save, plus tag
 * syncing, plus an upload, plus a queued job, inside a transaction. Leaving
 * that in the controller would make the controller untestable without HTTP
 * and would force us to duplicate it for the API or a future import command.
 * The controller's job is to turn a request into arguments; this class knows
 * what "create a post" means.
 */
final class PostService
{
    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $tagIds
     */
    public function create(array $data, User $author, array $tagIds = [], ?UploadedFile $cover = null): Post
    {
        // why a transaction: if tag syncing fails we must not be left with a
        // half-made post. Either all of it happens, or none of it.
        return DB::transaction(function () use ($data, $author, $tagIds, $cover): Post {
            $post = new Post($data);
            $post->user()->associate($author);

            if ($cover !== null) {
                $post->cover_image = $this->storeCover($cover);
            }

            $post->save();
            $post->tags()->sync($tagIds);

            $this->queueCoverOptimisation($post);

            return $post;
        });
    }

    /**
     * @param  array<string, mixed>  $data
     * @param  list<int>  $tagIds
     */
    public function update(Post $post, array $data, array $tagIds = [], ?UploadedFile $cover = null): Post
    {
        return DB::transaction(function () use ($post, $data, $tagIds, $cover): Post {
            $post->fill($data);

            if ($cover !== null) {
                $this->deleteCover($post);
                $post->cover_image = $this->storeCover($cover);
            }

            $post->save();
            $post->tags()->sync($tagIds);

            $this->queueCoverOptimisation($post);

            return $post;
        });
    }

    /** Soft delete - the post moves to the trash and can be restored. */
    public function delete(Post $post): void
    {
        $post->delete();
    }

    /** Permanent removal, including the file on disk. */
    public function forceDelete(Post $post): void
    {
        $this->deleteCover($post);
        $post->forceDelete();
    }

    /**
     * Store the upload under a name we generate ourselves.
     *
     * why not the original filename: it is attacker-controlled. A file called
     * "../../.env" or "evil.php.jpg" has no chance against a random hash.
     */
    private function storeCover(UploadedFile $file): string
    {
        return $file->store('covers', 'public');
    }

    private function deleteCover(Post $post): void
    {
        if (filled($post->cover_image)) {
            Storage::disk('public')->delete($post->cover_image);
        }
    }

    private function queueCoverOptimisation(Post $post): void
    {
        if (filled($post->cover_image)) {
            OptimizeCoverImage::dispatch($post->id, $post->cover_image);
        }
    }
}
