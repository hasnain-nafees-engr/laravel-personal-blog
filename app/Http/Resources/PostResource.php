<?php

declare(strict_types=1);

namespace App\Http\Resources;

use App\Models\Post;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * Shapes a Post for the public JSON API.
 *
 * why an API Resource instead of returning the model: `return $post` would
 * dump every column - including anything we add later, like an internal note
 * or an author's email. A resource is an explicit allow-list, and it lets the
 * JSON field names differ from the database column names.
 *
 * @mixin Post
 */
class PostResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->id,
            'title' => $this->title,
            'slug' => $this->slug,
            'excerpt' => $this->summary,
            'url' => route('posts.show', $this),
            'published_at' => $this->published_at?->toIso8601String(),
            'reading_time_minutes' => $this->reading_time,
            'view_count' => $this->view_count,

            // whenLoaded() prints the relation only if it was eager-loaded,
            // so a forgotten with() can never trigger a hidden N+1 query here.
            'author' => $this->whenLoaded('user', fn (): array => [
                'name' => $this->user->name,
            ]),

            'category' => $this->whenLoaded('category', fn (): ?array => $this->category === null ? null : [
                'name' => $this->category->name,
                'slug' => $this->category->slug,
            ]),

            'tags' => $this->whenLoaded('tags', fn (): array => $this->tags
                ->map(fn ($tag): array => ['name' => $tag->name, 'slug' => $tag->slug])
                ->all()),

            'comments_count' => $this->whenCounted('approvedComments'),

            // The full article body is only sent on the single-post endpoint.
            'body_html' => $this->when(
                $request->routeIs('api.posts.show'),
                fn (): string => $this->body_html,
            ),
        ];
    }
}
