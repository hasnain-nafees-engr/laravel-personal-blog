<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Http\Response;

/** RSS 2.0 feed of the latest articles. */
class FeedController extends Controller
{
    public function __invoke(): Response
    {
        $posts = Post::query()
            ->published()
            ->with('user')
            ->latest('published_at')
            ->limit(20)
            ->get();

        return response()
            ->view('feed', compact('posts'))
            ->header('Content-Type', 'application/rss+xml; charset=UTF-8');
    }
}
