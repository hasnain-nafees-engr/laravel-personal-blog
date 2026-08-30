<?php

declare(strict_types=1);

namespace App\Support;

/**
 * "5 min read" estimates.
 *
 * why: a plain static helper rather than an injected service - it has no
 * dependencies and no state, so the container would add ceremony for nothing.
 * Compare with MarkdownRenderer, which is an interface precisely because a
 * different implementation is imaginable.
 */
final class ReadingTime
{
    /** Average adult silent-reading speed, words per minute. */
    public const WORDS_PER_MINUTE = 200;

    public static function forText(string $text): int
    {
        $words = str_word_count(strip_tags($text));

        if ($words === 0) {
            return 0;
        }

        $wpm = (int) config('blog.reading_speed_wpm', self::WORDS_PER_MINUTE);

        return max(1, (int) ceil($words / max(1, $wpm)));
    }
}
