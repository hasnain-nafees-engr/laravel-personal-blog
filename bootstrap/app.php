<?php

use App\Http\Middleware\EnsureUserIsAdmin;
use App\Http\Middleware\SecurityHeaders;
use Illuminate\Foundation\Application;
use Illuminate\Foundation\Configuration\Exceptions;
use Illuminate\Foundation\Configuration\Middleware;
use Illuminate\Http\Request;

return Application::configure(basePath: dirname(__DIR__))
    ->withRouting(
        web: __DIR__.'/../routes/web.php',
        api: __DIR__.'/../routes/api.php',
        commands: __DIR__.'/../routes/console.php',
        health: '/up',
    )
    ->withMiddleware(function (Middleware $middleware): void {
        // Runs on every web response - see the class for what each header does.
        $middleware->web(append: [
            SecurityHeaders::class,
        ]);

        // why an alias: routes read ->middleware('admin') instead of the full
        // class name, and the mapping lives in one place.
        $middleware->alias([
            'admin' => EnsureUserIsAdmin::class,
        ]);

        // why: nginx sits in front of php-fpm, so without this every visitor
        // looks like the nginx container and per-IP rate limiting would treat
        // the whole internet as one person.
        $middleware->trustProxies(at: [
            '10.0.0.0/8',
            '172.16.0.0/12',
            '192.168.0.0/16',
        ]);
    })
    ->withExceptions(function (Exceptions $exceptions): void {
        $exceptions->shouldRenderJsonWhen(
            fn (Request $request) => $request->is('api/*') || $request->expectsJson(),
        );
    })->create();
