<?php

declare(strict_types=1);

namespace App\Http\Controllers;

use App\Models\Post;
use Illuminate\Contracts\View\View;

/**
 * The landing page.
 *
 * why an invokable (single __invoke method) controller: it has exactly one
 * action, so a class with an index() method would only add a name to
 * remember. The route reads Route::get('/', HomeController::class).
 */
class HomeController extends Controller
{
    public function __invoke(): View
    {
        $latest = Post::query()
            ->published()
            ->with(['user', 'category', 'tags'])
            ->withCount('approvedComments')
            ->latest('published_at')
            ->limit(7)
            ->get();

        return view('home', [
            // The newest article gets the big treatment; the rest are cards.
            'featured' => $latest->first(),
            'posts' => $latest->skip(1)->values(),
        ]);
    }
}
