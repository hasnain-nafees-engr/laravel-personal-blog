<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Comment;
use App\Models\User;

/**
 * Moderation rights. An author may moderate conversations under their own
 * articles; an admin may moderate everything.
 */
class CommentPolicy
{
    public function viewAny(User $user): bool
    {
        return true;
    }

    public function moderate(User $user, Comment $comment): bool
    {
        return $user->isAdmin() || $comment->post?->user_id === $user->id;
    }

    public function delete(User $user, Comment $comment): bool
    {
        return $this->moderate($user, $comment);
    }
}
