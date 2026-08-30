<?php

declare(strict_types=1);

namespace App\Listeners;

use App\Events\CommentSubmitted;
use App\Mail\NewCommentNotification;
use Illuminate\Contracts\Queue\ShouldQueue;
use Illuminate\Support\Facades\Mail;

/**
 * Emails the post's author that a comment is waiting for moderation.
 *
 * why ShouldQueue: talking to an SMTP server can take seconds, and the
 * visitor who just clicked "Post comment" should not sit and watch it.
 * Implementing this interface is all it takes - Laravel then hands the
 * listener to the queue, and our `queue` container runs it.
 */
class SendCommentNotification implements ShouldQueue
{
    /** Retry a flaky mail server a few times before giving up. */
    public int $tries = 3;

    public int $backoff = 10;

    public function handle(CommentSubmitted $event): void
    {
        $comment = $event->comment->loadMissing('post.user');
        $author = $comment->post?->user;

        if ($author === null) {
            return;
        }

        Mail::to($author->email)->send(new NewCommentNotification($comment));
    }
}
