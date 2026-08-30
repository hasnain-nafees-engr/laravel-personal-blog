<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Moderation state of a comment. New comments start as Pending, so nothing
 * a stranger types appears on the site before a human has seen it.
 */
enum CommentStatus: string
{
    case Pending = 'pending';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending review',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Pending => 'bg-ochre-100 text-ochre-700 dark:bg-ochre-700/20 dark:text-ochre-300',
            self::Approved => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
            self::Rejected => 'bg-rose-100 text-rose-700 dark:bg-rose-500/15 dark:text-rose-300',
        };
    }
}
