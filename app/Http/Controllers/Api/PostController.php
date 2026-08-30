<?php

declare(strict_types=1);

namespace App\Http\Controllers\Api;

use App\Http\Controllers\Controller;
use App\Http\Resources\PostResource;
use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Symfony\Component\HttpFoundation\Response;

/**
 * Read-only public JSON API.
 *
 * Sanctum is installed (php artisan install:api) and the User model carries
 * HasApiTokens, so protecting a future write endpoint is one middleware away:
 * ->middleware('auth:sanctum'). Nothing here needs auth, so nothing here has it.
 */
class PostController extends Controller
{
    /** GET /api/posts */
    public function index(Request $request): AnonymousResourceCollection
    {
        $posts = Post::query()
            ->published()
            ->with(['user', 'category', 'tags'])
            ->withCount('approvedComments')
            ->when($request->string('q')->toString(),
                fn ($q, string $term) => $q->search($term))
            ->when($request->string('category')->toString(),
                fn ($q, string $slug) => $q->whereHas('category',
                    fn ($c) => $c->where('slug', $slug)))
            ->latest('published_at')
            ->paginate(min($request->integer('per_page', 10), 50));

        // The paginator's meta (current_page, total, links) is added by Laravel.
        return PostResource::collection($posts);
    }

    /** GET /api/posts/{post} - bound by slug, same as the web route. */
    public function show(Post $post): PostResource
    {
        // A draft or scheduled post must look like it does not exist.
        abort_unless($post->isPublished(), Response::HTTP_NOT_FOUND);

        $post->load(['user', 'category', 'tags'])->loadCount('approvedComments');

        return new PostResource($post);
    }
}
