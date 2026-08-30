<?php

declare(strict_types=1);

namespace App\Policies;

use App\Models\Tag;
use App\Models\User;

class TagPolicy
{
    public function viewAny(User $user): bool
    {
        return $user->isAdmin();
    }

    public function create(User $user): bool
    {
        return $user->isAdmin();
    }

    public function update(User $user, Tag $tag): bool
    {
        return $user->isAdmin();
    }

    public function delete(User $user, Tag $tag): bool
    {
        return $user->isAdmin();
    }
}
