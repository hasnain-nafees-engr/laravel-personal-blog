<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Comment;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/** Raised when a visitor submits a comment (before moderation). */
class CommentSubmitted
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Comment $comment) {}
}
