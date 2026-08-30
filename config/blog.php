<?php

/**
 * Blog-specific settings.
 *
 * why this file exists: `env()` must only ever be called from inside config/.
 * Once `php artisan config:cache` runs (the entrypoint does it in production),
 * the .env file is no longer read at all - every env() call outside config/
 * would suddenly return null. Reading config('blog.per_page') is safe in any
 * environment; reading env('BLOG_PER_PAGE') in a controller is a time bomb.
 */
return [

    // Posts per page on the public index and archives.
    'per_page' => (int) env('BLOG_PER_PAGE', 9),

    // Posts per page in the admin tables.
    'admin_per_page' => (int) env('BLOG_ADMIN_PER_PAGE', 15),

    // Words per minute used for the "x min read" estimate.
    'reading_speed_wpm' => (int) env('BLOG_READING_SPEED_WPM', 200),

    // How many related posts to show under an article.
    'related_posts' => (int) env('BLOG_RELATED_POSTS', 3),

    // Cover images are resized down to this width, keeping aspect ratio.
    'cover_max_width' => (int) env('BLOG_COVER_MAX_WIDTH', 1600),

    // Maximum accepted upload size for a cover image, in kilobytes.
    'cover_max_kb' => (int) env('BLOG_COVER_MAX_KB', 2048),

    // Cache lifetime (seconds) for the homepage and sidebar queries.
    'cache_ttl' => (int) env('BLOG_CACHE_TTL', 600),

    // A guest comment must sit unanswered for at least this many seconds
    // before we believe a human typed it (bot honeypot timing check).
    'comment_min_seconds' => (int) env('BLOG_COMMENT_MIN_SECONDS', 3),

];
