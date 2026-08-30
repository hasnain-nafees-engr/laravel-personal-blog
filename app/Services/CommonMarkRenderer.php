<?php

declare(strict_types=1);

namespace App\Services;

use App\Contracts\MarkdownRenderer;
use Illuminate\Support\Str;

/**
 * Markdown rendering backed by league/commonmark (shipped with Laravel).
 *
 * The security decision lives here: raw HTML inside a post body is stripped,
 * never passed through. That is what makes `{!! $post->body_html !!}` safe in
 * a Blade template - by the time the string reaches the view it contains only
 * tags this renderer produced.
 */
final class CommonMarkRenderer implements MarkdownRenderer
{
    public function render(string $markdown): string
    {
        if (trim($markdown) === '') {
            return '';
        }

        return Str::markdown($markdown, [
            // Drop any <script>, <iframe> or onclick= an author pastes in.
            'html_input' => 'strip',
            // Refuse javascript: and data: URLs in links and images.
            'allow_unsafe_links' => false,
            'max_nesting_level' => 50,
        ]);
    }
}
