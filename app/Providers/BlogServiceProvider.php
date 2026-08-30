<?php

declare(strict_types=1);

namespace App\Providers;

use App\Contracts\MarkdownRenderer;
use App\Models\Comment;
use App\Models\Post;
use App\Observers\PostObserver;
use App\Services\CommonMarkRenderer;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Blade;
use Illuminate\Support\Facades\View;
use Illuminate\Support\ServiceProvider;

/**
 * Wiring that belongs to the blog itself, kept out of AppServiceProvider so
 * each provider has one job.
 */
class BlogServiceProvider extends ServiceProvider
{
    /**
     * register() runs first, for BINDINGS ONLY.
     *
     * why nothing may be resolved here: other providers have not booted yet,
     * so touching the database or a facade that needs config would explode.
     */
    public function register(): void
    {
        // Ask for the interface anywhere (constructor, app(), a route closure)
        // and the container hands back this implementation.
        $this->app->singleton(MarkdownRenderer::class, CommonMarkRenderer::class);
    }

    /**
     * boot() runs after every provider has registered - safe to use facades.
     */
    public function boot(): void
    {
        Post::observe(PostObserver::class);

        // why: lazy loading a relation issues one extra query per row (the
        // N+1 problem). Turning it into an exception outside production means
        // a missing with() fails loudly in dev and CI instead of quietly
        // costing 50 queries in production.
        Model::preventLazyLoading(! $this->app->isProduction());

        // A tiny Blade directive so views can ask "is this an admin?" without
        // reaching for the enum: @admin ... @endadmin
        Blade::if('admin', fn (): bool => auth()->user()?->isAdmin() ?? false);

        // A VIEW COMPOSER: runs every time the admin layout renders and feeds
        // it the pending-comment badge count.
        //
        // why not query inside the Blade file: a view that runs its own
        // queries is impossible to test in isolation and hides work from
        // whoever reads the controller. The composer keeps the query in PHP
        // while the layout stays a template.
        View::composer('layouts.admin', function ($view): void {
            $user = auth()->user();

            $view->with('pendingComments', Comment::query()
                ->pending()
                ->when($user !== null && ! $user->isAdmin(),
                    fn ($q) => $q->whereHas('post', fn ($p) => $p->where('user_id', $user->id)))
                ->count());
        });
    }
}
