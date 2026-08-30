<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use Illuminate\Http\Response;

/**
 * sitemap.xml for search engines.
 *
 * why hand-rolled instead of a package (spatie/laravel-sitemap): the whole
 * thing is one query and one Blade template. Adding a dependency - and its
 * upgrade cycle - to generate 30 lines of XML is not a trade worth making,
 * and in a review I can explain every line of this.
 */
class SitemapController extends Controller
{
    public function __invoke(): Response
    {
        $posts = Post::query()
            ->published()
            ->select(['slug', 'updated_at'])
            ->latest('published_at')
            ->get();

        $categories = Category::query()->select(['slug', 'updated_at'])->get();
        $tags = Tag::query()->select(['slug', 'updated_at'])->get();

        return response()
            ->view('sitemap', compact('posts', 'categories', 'tags'))
            ->header('Content-Type', 'application/xml; charset=UTF-8');
    }
}
