<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Rules\Honeypot;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

class StoreCommentRequest extends FormRequest
{
    /** Commenting is open to everyone, including guests. */
    public function authorize(): bool
    {
        return true;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $post = $this->route('post');

        return [
            'author_name' => ['required', 'string', 'max:80'],
            'author_email' => ['required', 'email:rfc', 'max:180'],
            'body' => ['required', 'string', 'min:3', 'max:2000'],

            // A reply must point at a comment on THIS post - otherwise a
            // crafted request could graft a reply onto someone else's thread.
            'parent_id' => [
                'nullable', 'integer',
                Rule::exists('comments', 'id')->where('post_id', $post?->id),
            ],

            // Bot traps - see the Honeypot rule for how they work.
            'website' => [new Honeypot],
            'started_at' => ['required', 'string'],
        ];
    }

    protected function prepareForValidation(): void
    {
        // Logged-in users do not fill in their own name and email.
        if ($user = $this->user()) {
            $this->merge([
                'author_name' => $user->name,
                'author_email' => $user->email,
            ]);
        }
    }
}
