<?php

declare(strict_types=1);

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * Adds the defensive HTTP headers a browser understands.
 *
 * These cost nothing and shut down whole classes of attack, so they are
 * applied to every web response rather than route by route.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);

        // Never let a browser guess a response is JavaScript when we said it
        // was an image - that is how an uploaded "image" becomes a script.
        $response->headers->set('X-Content-Type-Options', 'nosniff');

        // No framing: kills clickjacking of the admin panel.
        $response->headers->set('X-Frame-Options', 'DENY');

        // Send the full URL only to ourselves; other origins see the origin.
        $response->headers->set('Referrer-Policy', 'strict-origin-when-cross-origin');

        // We use none of these APIs; deny them so an injected script cannot.
        $response->headers->set(
            'Permissions-Policy',
            'camera=(), microphone=(), geolocation=(), payment=(), usb=()',
        );

        // why: HSTS only over real HTTPS. Sending it on plain http://localhost
        // would pin the developer's browser to https for the whole domain.
        if ($request->secure()) {
            $response->headers->set(
                'Strict-Transport-Security',
                'max-age=31536000; includeSubDomains',
            );
        }

        return $response;
    }
}
