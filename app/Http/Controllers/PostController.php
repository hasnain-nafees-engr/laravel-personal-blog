<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use App\Services\BlogQueries;
use Illuminate\Contracts\View\View;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * The public article pages.
 *
 * Thin on purpose: fetch, hand to a view. Anything with a rule in it lives in
 * a scope (Post::published), a service (BlogQueries) or the model (recordView).
 */
class PostController extends Controller
{
    public function __construct(private readonly BlogQueries $queries) {}

    /** Paginated list of published articles. */
    public function index(): View
    {
        // why with() and withCount(): without them Blade would ask the
        // database for the author, the category and the comment count of
        // every single post while rendering - the N+1 problem. Two extra
        // queries here replace roughly 3 x perPage queries in the view.
        $posts = Post::query()
            ->published()
            ->with(['user', 'category', 'tags'])
            ->withCount('approvedComments')
            ->latest('published_at')
            ->paginate((int) config('blog.per_page'));

        return view('posts.index', [
            'posts' => $posts,
            'heading' => __('blog.all_posts'),
        ]);
    }

    /**
     * A single article, resolved by slug through route model binding.
     *
     * The {post} parameter is turned into a Post by Laravel because the model
     * declares getRouteKeyName() = 'slug'. A slug that does not exist is a 404
     * before this method ever runs.
     */
    public function show(Request $request, Post $post): View
    {
        // Route model binding does not know about editorial state, so the
        // "is this public?" rule is enforced here: a draft or scheduled post
        // must look to the world exactly like a URL that does not exist.
        abort_unless($post->isPublished(), Response::HTTP_NOT_FOUND);

        $post->load(['user', 'category', 'tags', 'approvedComments.approvedReplies']);

        $this->countViewOnce($request, $post);

        return view('posts.show', [
            'post' => $post,
            'related' => $this->queries->relatedPosts($post),
            // why passed explicitly rather than left to `$isPreview ?? false`
            // in the template: the view then always receives every variable it
            // uses, so a typo fails loudly instead of silently reading null.
            'isPreview' => false,
        ]);
    }

    /**
     * Count a view at most once per session per post.
     *
     * why: without this, one reader hitting refresh ten times looks like ten
     * readers, and the "popular posts" idea becomes meaningless.
     */
    private function countViewOnce(Request $request, Post $post): void
    {
        $key = 'viewed_post_'.$post->id;

        if ($request->session()->has($key)) {
            return;
        }

        $request->session()->put($key, true);
        $post->recordView();
    }
}
