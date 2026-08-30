<?php

declare(strict_types=1);

namespace App\Mail;

use App\Models\Comment;
use Illuminate\Bus\Queueable;
use Illuminate\Mail\Mailable;
use Illuminate\Mail\Mailables\Content;
use Illuminate\Mail\Mailables\Envelope;
use Illuminate\Queue\SerializesModels;

/**
 * "Someone commented on your post" - a Markdown mailable.
 *
 * why Markdown: Laravel renders it through pre-built, responsive email
 * components, so we write content instead of fighting table-based HTML that
 * survives Outlook.
 */
class NewCommentNotification extends Mailable
{
    use Queueable, SerializesModels;

    public function __construct(public readonly Comment $comment) {}

    public function envelope(): Envelope
    {
        return new Envelope(
            subject: 'New comment on "'.($this->comment->post->title ?? 'your post').'"',
            // Replies go to the person who wrote the comment, not to the app.
            replyTo: [$this->comment->author_email],
        );
    }

    public function content(): Content
    {
        return new Content(
            markdown: 'mail.comments.new',
            with: [
                'comment' => $this->comment,
                'post' => $this->comment->post,
                'moderationUrl' => route('admin.comments.index'),
            ],
        );
    }
}
