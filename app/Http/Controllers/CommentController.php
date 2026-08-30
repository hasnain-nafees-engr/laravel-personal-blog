<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Enums\CommentStatus;
use App\Events\CommentSubmitted;
use App\Http\Requests\StoreCommentRequest;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Http\RedirectResponse;
use Symfony\Component\HttpFoundation\Response;

class CommentController extends Controller
{
    /**
     * Accept a comment from the public form.
     *
     * Validation, the honeypot and the "reply belongs to this post" check all
     * happened in StoreCommentRequest before this method ran. Rate limiting
     * happened in the route's throttle:comments middleware.
     */
    public function store(StoreCommentRequest $request, Post $post): RedirectResponse
    {
        // A draft or scheduled post must look like it does not exist.
        abort_unless($post->isPublished(), Response::HTTP_NOT_FOUND);

        $comment = new Comment($request->safe()->only([
            'author_name', 'author_email', 'body', 'parent_id',
        ]));

        $comment->post()->associate($post);
        $comment->user()->associate($request->user());

        // why always Pending: nothing a stranger types reaches the site
        // before a human has read it. Moderation is the whole point.
        $comment->setAttribute('status', CommentStatus::Pending);
        $comment->save();

        CommentSubmitted::dispatch($comment);

        return redirect()
            ->route('posts.show', $post)
            ->withFragment('comments')
            ->with('status', __('blog.comment_submitted'));
    }
}
