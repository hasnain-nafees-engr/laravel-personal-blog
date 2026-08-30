<?php

declare(strict_types=1);

namespace App\Support;

/**
 * Every cache key the blog uses, in one place.
 *
 * why: cache invalidation goes wrong when keys are typed as string literals
 * in two places and only one of them gets cleared. Naming them here means the
 * observer that forgets a key fails to compile, not silently in production.
 *
 * Note the database cache store has no tag support, so we forget keys
 * explicitly rather than flushing a tag.
 */
final class CacheKeys
{
    public const FEATURED_POST = 'blog:featured-post';

    public const SIDEBAR_CATEGORIES = 'blog:sidebar-categories';

    public const SIDEBAR_TAGS = 'blog:sidebar-tags';

    public const DASHBOARD_COUNTS = 'blog:dashboard-counts';

    public const ARCHIVE_MONTHS = 'blog:archive-months';

    /**
     * Keys that any change to a post invalidates.
     *
     * @return list<string>
     */
    public static function postRelated(): array
    {
        return [
            self::FEATURED_POST,
            self::SIDEBAR_CATEGORIES,
            self::SIDEBAR_TAGS,
            self::DASHBOARD_COUNTS,
            self::ARCHIVE_MONTHS,
        ];
    }
}
