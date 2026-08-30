<?php

use App\Services\CommonMarkRenderer;

beforeEach(function () {
    $this->renderer = new CommonMarkRenderer;
});

it('renders basic markdown to html', function () {
    $html = $this->renderer->render("# Title\n\nSome **bold** text.");

    expect($html)
        ->toContain('<h1>Title</h1>')
        ->toContain('<strong>bold</strong>');
});

it('returns an empty string for blank input', function () {
    expect($this->renderer->render(''))->toBe('')
        ->and($this->renderer->render('   '))->toBe('');
});

/*
 * These are the tests that justify using {!! !!} in posts/show.blade.php.
 * If any of them ever fails, that unescaped output becomes a stored-XSS hole.
 */
it('strips raw html so script tags cannot be injected', function () {
    $html = $this->renderer->render('Hello <script>alert("xss")</script> world');

    expect($html)
        ->not->toContain('<script>')
        ->not->toContain('alert("xss")');
});

it('strips inline event handlers', function () {
    $html = $this->renderer->render('<img src="x" onerror="alert(1)">');

    expect($html)->not->toContain('onerror');
});

it('refuses javascript urls in links', function () {
    $html = $this->renderer->render('[click me](javascript:alert(1))');

    expect($html)->not->toContain('javascript:');
});

it('still allows safe links', function () {
    $html = $this->renderer->render('[Laravel](https://laravel.com)');

    expect($html)->toContain('href="https://laravel.com"');
});
