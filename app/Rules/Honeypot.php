<?php

declare(strict_types=1);

namespace App\Rules;

use Closure;
use Illuminate\Contracts\Encryption\DecryptException;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Support\Facades\Crypt;

/**
 * A two-part bot trap for the public comment form.
 *
 * 1. A field named "website" is hidden with CSS. A human never sees it, so it
 *    must arrive empty. Bots fill every input they find.
 * 2. An encrypted timestamp records when the form was rendered. A human takes
 *    at least a few seconds to type a comment; a script posts instantly.
 *    Encrypting it means a bot cannot simply back-date the value.
 *
 * why this on top of rate limiting: rate limiting caps how OFTEN someone can
 * post; this catches whether a human was involved at all - and it costs the
 * visitor nothing, unlike a CAPTCHA.
 */
class Honeypot implements ValidationRule
{
    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        // 1. The decoy field must be untouched.
        if (filled($value)) {
            $fail(__('blog.comment_rejected'));

            return;
        }

        // 2. The form must have been on screen long enough to type in.
        $renderedAt = $this->renderedAt(request()->input('started_at'));

        if ($renderedAt === null) {
            $fail(__('blog.comment_rejected'));

            return;
        }

        if ((now()->timestamp - $renderedAt) < (int) config('blog.comment_min_seconds', 3)) {
            $fail(__('blog.comment_too_fast'));
        }
    }

    /** Decrypt the timestamp the form was stamped with, or null if tampered. */
    private function renderedAt(mixed $token): ?int
    {
        if (! is_string($token) || $token === '') {
            return null;
        }

        try {
            return (int) Crypt::decryptString($token);
        } catch (DecryptException) {
            return null;
        }
    }
}
