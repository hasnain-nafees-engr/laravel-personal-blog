<?php

namespace App\Providers;

use App\Events\CommentSubmitted;
use App\Events\PostPublished;
use App\Listeners\LogPostPublication;
use App\Listeners\SendCommentNotification;
use Illuminate\Cache\RateLimiting\Limit;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Facades\RateLimiter;
use Illuminate\Support\Facades\URL;
use Illuminate\Support\ServiceProvider;

class AppServiceProvider extends ServiceProvider
{
    public function register(): void
    {
        //
    }

    public function boot(): void
    {
        $this->registerGates();
        $this->registerRateLimiters();
        $this->registerEventListeners();

        // Behind nginx the app cannot tell it was reached over https, which
        // would make every generated asset URL http:// on a TLS site.
        if ($this->app->isProduction()) {
            URL::forceScheme('https');
        }
    }

    /**
     * Gates answer yes/no questions that are not about a specific model.
     * (A Policy is the same idea, but scoped to one model class.)
     */
    private function registerGates(): void
    {
        Gate::define('access-admin', fn ($user): bool => $user->isAdmin());

        // why NOT Gate::before(): a blanket admin override would also swallow
        // policy methods that deliberately say no to admins, and it makes the
        // real rule invisible when you read the policy. Each policy asks
        // isAdmin() itself, so the logic stays where you look for it.
    }

    private function registerRateLimiters(): void
    {
        // Comments: a human writing thoughtful replies never needs more.
        RateLimiter::for('comments', fn (Request $request) => Limit::perMinute(3)
            ->by($request->ip())
            ->response(fn () => back()
                ->withInput()
                ->withErrors(['body' => __('blog.comment_rate_limited')])));

        // Read-only public API.
        RateLimiter::for('api', fn (Request $request) => Limit::perMinute(60)
            ->by($request->user()?->id ?: $request->ip()));

        // Note: Breeze already throttles login (5 attempts per minute keyed by
        // email + IP) inside App\Http\Requests\Auth\LoginRequest.
    }

    /**
     * why explicit registration instead of Laravel's automatic event
     * discovery: `php artisan event:list` shows the whole wiring, and a
     * reviewer can read this file to learn what happens when a post is
     * published. Discovery is convenient but hides the graph.
     */
    private function registerEventListeners(): void
    {
        Event::listen(PostPublished::class, LogPostPublication::class);
        Event::listen(CommentSubmitted::class, SendCommentNotification::class);
    }
}
