<?php

namespace Database\Seeders;

use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Str;

class DatabaseSeeder extends Seeder
{
    /**
     * Build a believable demo blog.
     *
     * why firstOrCreate for the accounts: `db:seed` can be run twice without
     * blowing up on the unique email index. The bulk content uses factories,
     * which is the point of factories - realistic data without fixtures
     * checked into the repo.
     */
    public function run(): void
    {
        $admin = User::firstOrCreate(
            ['email' => 'admin@example.com'],
            [
                'name' => 'Hasnain Nafees',
                'password' => 'password',   // hashed by the model's 'hashed' cast
                'role' => UserRole::Admin,
                'email_verified_at' => now(),
            ],
        );

        $author = User::firstOrCreate(
            ['email' => 'author@example.com'],
            [
                'name' => 'Sana Author',
                'password' => 'password',
                'role' => UserRole::Author,
                'email_verified_at' => now(),
            ],
        );

        $categories = collect([
            ['name' => 'Engineering', 'description' => 'Notes from building and shipping software.'],
            ['name' => 'Laravel', 'description' => 'Patterns, pitfalls and the framework internals.'],
            ['name' => 'Data', 'description' => 'Pipelines, warehouses and the occasional SQL rabbit hole.'],
            ['name' => 'Career', 'description' => 'Interviews, teams and growing as an engineer.'],
        ])->map(fn (array $data): Category => Category::firstOrCreate(
            ['slug' => Str::slug($data['name'])],
            ['name' => $data['name'], 'description' => $data['description']],
        ));

        $tags = collect([
            'php', 'docker', 'postgres', 'testing', 'eloquent',
            'performance', 'security', 'devops', 'livewire', 'tailwind',
        ])->map(fn (string $name): Tag => Tag::firstOrCreate(
            ['slug' => Str::slug($name)],
            ['name' => Str::title($name)],
        ));

        // 24 published, 4 drafts, 2 scheduled - enough to page through and to
        // prove that drafts and scheduled posts stay off the public site.
        $published = Post::factory()
            ->count(24)
            ->published()
            ->recycle([$admin, $author])
            ->recycle($categories)
            ->create();

        Post::factory()->count(4)->draft()->recycle([$admin, $author])->recycle($categories)->create();
        Post::factory()->count(2)->scheduled()->recycle([$admin, $author])->recycle($categories)->create();

        // Attach 2-4 random tags to every published post.
        $published->each(function (Post $post) use ($tags): void {
            $post->tags()->sync($tags->random(random_int(2, 4))->pluck('id'));
        });

        // Comments: a mix of approved threads, replies and pending moderation.
        $published->take(12)->each(function (Post $post): void {
            $roots = Comment::factory()
                ->count(random_int(1, 3))
                ->approved()
                ->for($post)
                ->create();

            foreach ($roots as $root) {
                if (random_int(0, 1) === 1) {
                    Comment::factory()->approved()->replyTo($root)->create();
                }
            }

            if (random_int(0, 2) === 0) {
                Comment::factory()->pending()->for($post)->create();
            }
        });

        $this->command?->info('Seeded: admin@example.com / author@example.com - password: "password"');
    }
}
