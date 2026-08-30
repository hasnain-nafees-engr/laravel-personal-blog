<?php

declare(strict_types=1);

namespace App\Events;

use App\Models\Post;
use Illuminate\Foundation\Events\Dispatchable;
use Illuminate\Queue\SerializesModels;

/**
 * Raised the moment a post becomes publicly visible.
 *
 * why an event: publishing has side effects that will keep growing (clear
 * caches, write an audit line, later maybe a newsletter). The publishing code
 * should not have to know about any of them - it announces what happened and
 * listeners decide what that means.
 */
class PostPublished
{
    use Dispatchable, SerializesModels;

    public function __construct(public readonly Post $post) {}
}
