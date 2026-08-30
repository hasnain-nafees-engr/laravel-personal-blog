<?php

declare(strict_types=1);

namespace App\Http\Requests;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Foundation\Http\FormRequest;
use Illuminate\Validation\Rule;

/**
 * Validation for creating a post.
 *
 * why a Form Request rather than $request->validate() in the controller:
 * the rules are reusable (Store/Update share most of them), the controller
 * stays about orchestration, authorisation happens before validation, and
 * the rules can be unit-tested without touching a controller.
 */
class StorePostRequest extends FormRequest
{
    public function authorize(): bool
    {
        return $this->user()?->can('create', Post::class) ?? false;
    }

    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:180'],
            'slug' => ['nullable', 'string', 'max:200', 'alpha_dash', Rule::unique('posts', 'slug')],
            'excerpt' => ['nullable', 'string', 'max:500'],
            'body' => ['required', 'string', 'min:20'],
            'category_id' => ['nullable', 'integer', Rule::exists('categories', 'id')],
            'tags' => ['array', 'max:8'],
            'tags.*' => ['integer', Rule::exists('tags', 'id')],
            'status' => ['required', Rule::enum(PostStatus::class)],

            // A scheduled post is meaningless without a future date.
            'published_at' => [
                'nullable',
                'date',
                Rule::requiredIf(fn (): bool => $this->input('status') === PostStatus::Scheduled->value),
            ],

            // why both mimes and max: `mimes` checks the real file type, not
            // the extension the browser claimed, and `max` stops a 40 MB
            // upload from filling the disk.
            'cover_image' => [
                'nullable',
                'image',
                'mimes:jpg,jpeg,png,webp',
                'max:'.config('blog.cover_max_kb'),
                'dimensions:min_width=400,min_height=200',
            ],
        ];
    }

    /**
     * @return array<string, string>
     */
    public function messages(): array
    {
        return [
            'published_at.required' => 'A scheduled post needs a date and time to go live.',
            'cover_image.dimensions' => 'The cover image must be at least 400x200 pixels.',
        ];
    }

    /**
     * Runs BEFORE the rules - normalises input so validation sees clean data.
     */
    protected function prepareForValidation(): void
    {
        $this->merge([
            'slug' => filled($this->input('slug')) ? str($this->input('slug'))->slug()->value() : null,
            'tags' => array_filter((array) $this->input('tags', [])),
        ]);
    }

    /**
     * The attributes that may be mass-assigned to the model.
     *
     * @return array<string, mixed>
     */
    public function postAttributes(): array
    {
        $data = $this->safe()->only(['title', 'slug', 'excerpt', 'body', 'category_id', 'status']);

        // A post published without an explicit date goes live now.
        $data['published_at'] = match ($this->enum('status', PostStatus::class)) {
            PostStatus::Published => $this->date('published_at') ?? now(),
            PostStatus::Scheduled => $this->date('published_at'),
            PostStatus::Draft => null,
            default => null,
        };

        return $data;
    }

    /** @return list<int> */
    public function tagIds(): array
    {
        return array_map('intval', $this->safe()->array('tags'));
    }
}
