<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\CommentStatus;
use App\Http\Controllers\Controller;
use App\Models\ActivityLog;
use App\Models\Comment;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

class CommentController extends Controller
{
    /** Moderation queue, newest first, pending by default. */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Comment::class);

        $status = $request->string('status')->toString() ?: CommentStatus::Pending->value;

        $comments = Comment::query()
            ->with(['post:id,title,slug,user_id', 'user'])
            ->when($status !== 'all', fn ($q) => $q->where('status', $status))
            // Authors moderate only their own posts' comments.
            ->unless($request->user()->isAdmin(),
                fn ($q) => $q->whereHas('post', fn ($p) => $p->where('user_id', $request->user()->id)))
            ->latest()
            ->paginate((int) config('blog.admin_per_page'))
            ->withQueryString();

        return view('admin.comments.index', [
            'comments' => $comments,
            'currentStatus' => $status,
            'statuses' => CommentStatus::cases(),
        ]);
    }

    public function approve(Comment $comment): RedirectResponse
    {
        $this->authorize('moderate', $comment);

        $comment->update(['status' => CommentStatus::Approved]);
        ActivityLog::record('comment.approved', $comment);

        return back()->with('status', __('blog.comment_approved'));
    }

    public function reject(Comment $comment): RedirectResponse
    {
        $this->authorize('moderate', $comment);

        $comment->update(['status' => CommentStatus::Rejected]);
        ActivityLog::record('comment.rejected', $comment);

        return back()->with('status', __('blog.comment_rejected_admin'));
    }

    public function destroy(Comment $comment): RedirectResponse
    {
        $this->authorize('delete', $comment);

        // Replies cascade away with the parent - see the comments migration.
        $comment->delete();

        return back()->with('status', __('blog.comment_deleted'));
    }
}
