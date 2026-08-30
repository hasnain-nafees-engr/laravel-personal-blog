<?php

declare(strict_types=1);

namespace App\Enums;

/**
 * Who a user is allowed to be.
 *
 * why: a backed enum instead of a plain string column means invalid roles
 * cannot exist in PHP land - `UserRole::from('wizard')` throws instead of
 * silently granting nothing (or everything).
 */
enum UserRole: string
{
    case Admin = 'admin';
    case Author = 'author';

    /** Human label for dropdowns and badges. */
    public function label(): string
    {
        return match ($this) {
            self::Admin => 'Administrator',
            self::Author => 'Author',
        };
    }

    /** Tailwind classes used by the badge component. */
    public function badgeClasses(): string
    {
        return match ($this) {
            self::Admin => 'bg-ochre-100 text-ochre-700 dark:bg-ochre-700/20 dark:text-ochre-300',
            self::Author => 'bg-paper-200 text-ink-600 dark:bg-ink-800 dark:text-paper-300',
        };
    }
}
