<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Editorial state of a post.
 *
 * Draft      - work in progress, never public.
 * Scheduled  - finished, waiting for `published_at` to arrive. The
 *              `posts:publish-scheduled` command flips it to Published.
 * Published  - public as soon as `published_at` is in the past.
 */
enum PostStatus: string
{
    case Draft = 'draft';
    case Scheduled = 'scheduled';
    case Published = 'published';

    public function label(): string
    {
        return match ($this) {
            self::Draft => 'Draft',
            self::Scheduled => 'Scheduled',
            self::Published => 'Published',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::Draft => 'bg-paper-200 text-ink-600 dark:bg-ink-800 dark:text-paper-300',
            self::Scheduled => 'bg-ochre-100 text-ochre-700 dark:bg-ochre-700/20 dark:text-ochre-300',
            self::Published => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-500/15 dark:text-emerald-300',
        };
    }

    /** Values usable in a validation rule or a <select>. */
    public static function values(): array
    {
        return array_column(self::cases(), 'value');
    }
}
