<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Post;
use App\Models\User;

/**
 * Who may do what to a post.
 *
 * Laravel finds this class by convention (Post -> PostPolicy) and every
 * `$this->authorize('update', $post)` in a controller lands in the matching
 * method here. A method returning false becomes a 403.
 */
class PostPolicy
{
    /** Anyone signed in may open the admin post list (they see their own). */
    public function viewAny(User $user): bool
    {
        return true;
    }

    /**
     * Reading an unpublished post (draft preview) is limited to its author
     * and admins. Published posts are handled by the public controller, which
     * never consults a policy - no user, nothing to authorise.
     */
    public function view(User $user, Post $post): bool
    {
        return $user->isAdmin() || $this->owns($user, $post);
    }

    public function create(User $user): bool
    {
        return true;
    }

    public function update(User $user, Post $post): bool
    {
        return $user->isAdmin() || $this->owns($user, $post);
    }

    public function delete(User $user, Post $post): bool
    {
        return $user->isAdmin() || $this->owns($user, $post);
    }

    public function restore(User $user, Post $post): bool
    {
        return $user->isAdmin() || $this->owns($user, $post);
    }

    /** why: permanent deletion is admin-only - it cannot be undone. */
    public function forceDelete(User $user, Post $post): bool
    {
        return $user->isAdmin();
    }

    private function owns(User $user, Post $post): bool
    {
        return $post->user_id === $user->id;
    }
}
