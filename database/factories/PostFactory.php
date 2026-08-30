<?php

namespace Database\Factories;

use App\Enums\PostStatus;
use App\Models\Category;
use App\Models\Post;
use App\Models\User;
use Illuminate\Database\Eloquent\Factories\Factory;
use Illuminate\Support\Str;

/**
 * @extends Factory<Post>
 */
class PostFactory extends Factory
{
    public function definition(): array
    {
        $title = rtrim(fake()->unique()->sentence(random_int(4, 8)), '.');

        return [
            'user_id' => User::factory(),
            'category_id' => Category::factory(),
            'title' => $title,
            'slug' => Str::slug($title).'-'.Str::lower(Str::random(6)),
            'excerpt' => fake()->paragraph(2),
            'body' => $this->markdownBody(),
            'cover_image' => null,
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year', '-1 day'),
            'view_count' => fake()->numberBetween(0, 4000),
        ];
    }

    public function published(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Published,
            'published_at' => fake()->dateTimeBetween('-1 year', '-1 hour'),
        ]);
    }

    public function draft(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Draft,
            'published_at' => null,
        ]);
    }

    /** Finished, waiting for its publish date in the future. */
    public function scheduled(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Scheduled,
            'published_at' => fake()->dateTimeBetween('+1 hour', '+2 months'),
        ]);
    }

    /** Scheduled but already due - what the publish command must pick up. */
    public function dueForPublishing(): static
    {
        return $this->state(fn (array $attributes): array => [
            'status' => PostStatus::Scheduled,
            'published_at' => now()->subMinute(),
        ]);
    }

    public function withCover(): static
    {
        return $this->state(fn (array $attributes): array => [
            'cover_image' => 'covers/placeholder.jpg',
        ]);
    }

    /** Realistic Markdown so rendering, reading time and prose styles get exercised. */
    private function markdownBody(): string
    {
        $sections = [];

        foreach (range(1, random_int(3, 5)) as $i) {
            $sections[] = '## '.rtrim(fake()->sentence(4), '.');
            $sections[] = fake()->paragraph(6);
            $sections[] = fake()->paragraph(5);

            if ($i === 2) {
                $sections[] = '- '.fake()->sentence(6)."\n- ".fake()->sentence(5)."\n- ".fake()->sentence(7);
            }

            if ($i === 3) {
                $sections[] = '> '.fake()->sentence(12);
                $sections[] = "```php\n\$posts = Post::published()->with('category')->paginate();\n```";
            }
        }

        return implode("\n\n", $sections);
    }
}
