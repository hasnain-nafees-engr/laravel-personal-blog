<?php

declare(strict_types=1);

namespace App\Http\Requests;

use Illuminate\Validation\Rule;

/**
 * Same rules as creating, with two differences:
 *  - authorisation checks THIS post (ownership), not just "may create".
 *  - the slug uniqueness rule must ignore the post being edited, or saving
 *    an unchanged post would fail "slug already taken" against itself.
 */
class UpdatePostRequest extends StorePostRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('update', $this->route('post')) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        $rules = parent::rules();

        $rules['slug'] = [
            'nullable', 'string', 'max:200', 'alpha_dash',
            Rule::unique('posts', 'slug')->ignore($this->route('post')),
        ];

        return $rules;
    }
}
