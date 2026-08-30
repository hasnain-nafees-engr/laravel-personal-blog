<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Category;
use Illuminate\Contracts\View\View;

class CategoryController extends Controller
{
    /** Archive page for one category, bound by slug. */
    public function show(Category $category): View
    {
        $posts = $category->posts()
            ->published()
            ->with(['user', 'category', 'tags'])
            ->withCount('approvedComments')
            ->latest('published_at')
            ->paginate((int) config('blog.per_page'));

        return view('posts.index', [
            'posts' => $posts,
            'heading' => $category->name,
            'description' => $category->description,
            'emptyMessage' => __('blog.no_posts_in_category'),
        ]);
    }
}
