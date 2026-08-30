<?php

declare(strict_types=1);

namespace App\Contracts;

/**
 * Turns Markdown source into HTML that is safe to print unescaped.
 *
 * why: an interface rather than a concrete class, so the container can swap
 * the implementation (a different parser, a caching decorator, a fake in
 * tests) without a single call site changing.
 */
interface MarkdownRenderer
{
    public function render(string $markdown): string;
}
