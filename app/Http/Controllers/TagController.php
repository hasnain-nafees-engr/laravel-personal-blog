<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Tag;
use Illuminate\Contracts\View\View;

class TagController extends Controller
{
    /** Archive page for one tag, bound by slug. */
    public function show(Tag $tag): View
    {
        $posts = $tag->posts()
            ->published()
            ->with(['user', 'category', 'tags'])
            ->withCount('approvedComments')
            ->latest('published_at')
            ->paginate((int) config('blog.per_page'));

        return view('posts.index', [
            'posts' => $posts,
            'heading' => '#'.$tag->name,
            'emptyMessage' => __('blog.no_posts_with_tag'),
        ]);
    }
}
