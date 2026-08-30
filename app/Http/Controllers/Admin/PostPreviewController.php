<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Models\Post;
use App\Services\BlogQueries;
use Illuminate\Contracts\View\View;

/**
 * Renders an unpublished post exactly as readers will see it.
 *
 * why not just visit the public URL: PostController::show() refuses anything
 * that is not published (that is the rule protecting drafts). This route
 * reuses the same view but authorises through PostPolicy::view first, so only
 * the author or an admin gets in.
 */
class PostPreviewController extends Controller
{
    public function __construct(private readonly BlogQueries $queries) {}

    public function __invoke(Post $post): View
    {
        $this->authorize('view', $post);

        $post->load(['user', 'category', 'tags', 'approvedComments.approvedReplies']);

        return view('posts.show', [
            'post' => $post,
            'related' => $this->queries->relatedPosts($post),
            'isPreview' => true,
        ]);
    }
}
