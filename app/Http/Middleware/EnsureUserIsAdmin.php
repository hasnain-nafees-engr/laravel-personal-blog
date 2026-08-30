<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Symfony\Component\HttpFoundation\Response;

/**
 * Custom middleware: blocks non-admins from admin-only routes.
 *
 * why middleware and not a policy: a policy answers "may this user touch
 * THIS record?". This is a coarser question - "does this user belong in the
 * admin area at all?" - and it must be answered before any controller runs.
 *
 * Registered as the alias 'admin' in bootstrap/app.php.
 */
class EnsureUserIsAdmin
{
    public function handle(Request $request, Closure $next): Response
    {
        // Delegates to the Gate defined in AppServiceProvider, so the rule
        // lives in exactly one place and @can('access-admin') in a Blade
        // view gives the same answer as this middleware.
        if (! Gate::allows('access-admin')) {
            abort(403, 'This area is for administrators only.');
        }

        return $next($request);
    }
}
