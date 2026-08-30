<?php

use App\Models\Category;
use App\Models\Post;

it('serves a sitemap as xml', function () {
    $post = Post::factory()->published()->create();
    $category = Category::factory()->create();

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/xml; charset=UTF-8')
        ->assertSee(route('posts.show', $post))
        ->assertSee(route('categories.show', $category));
});

it('keeps unpublished posts out of the sitemap', function () {
    $draft = Post::factory()->draft()->create();

    $this->get(route('sitemap'))
        ->assertOk()
        ->assertDontSee(route('posts.show', $draft));
});

it('serves an rss feed', function () {
    $post = Post::factory()->published()->create(['title' => 'A syndicated article']);

    $this->get(route('feed'))
        ->assertOk()
        ->assertHeader('Content-Type', 'application/rss+xml; charset=UTF-8')
        ->assertSee('A syndicated article')
        ->assertSee('<rss version="2.0"', escape: false);
});

it('keeps unpublished posts out of the feed', function () {
    Post::factory()->draft()->create(['title' => 'Unreleased draft']);

    $this->get(route('feed'))->assertOk()->assertDontSee('Unreleased draft');
});

it('puts open graph tags on a post page', function () {
    $post = Post::factory()->published()->create(['title' => 'Shareable article']);

    $this->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('property="og:title"', escape: false)
        ->assertSee('property="og:type" content="article"', escape: false)
        ->assertSee('rel="canonical"', escape: false)
        ->assertSee('name="twitter:card"', escape: false);
});

it('sets a page title from the post', function () {
    $post = Post::factory()->published()->create(['title' => 'A precise headline']);

    // why e(): the app name contains an apostrophe ("Hasnain's Blog"), which
    // Blade escapes to &#039; in the rendered HTML. Comparing against the raw
    // string would fail for the right reason - the output really is escaped.
    $this->get(route('posts.show', $post))
        ->assertOk()
        ->assertSee('<title>A precise headline — '.e(config('app.name')).'</title>', escape: false);
});

it('sends the security headers on every page', function () {
    $this->get(route('home'))
        ->assertOk()
        ->assertHeader('X-Content-Type-Options', 'nosniff')
        ->assertHeader('X-Frame-Options', 'DENY')
        ->assertHeader('Referrer-Policy', 'strict-origin-when-cross-origin');
});

it('does not send HSTS over plain http', function () {
    // why: sending it on http://localhost would pin a developer's browser to
    // https for the whole domain, which is very annoying to undo.
    $this->get(route('home'))->assertOk()->assertHeaderMissing('Strict-Transport-Security');
});
