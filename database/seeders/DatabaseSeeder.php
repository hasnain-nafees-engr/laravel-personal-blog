<?php

namespace Database\Seeders;

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;
use App\Models\Category;
use App\Models\Comment;
use App\Models\Post;
use App\Models\Tag;
use App\Models\User;
use Illuminate\Database\Seeder;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

/**
 * Builds the demo blog from real content.
 *
 * why real articles and not Faker: lorem ipsum tells a reader nothing about
 * how the site reads, and it has no headings, code blocks, lists or links - so
 * the prose styles, the reading-time estimate and the Markdown renderer are
 * never honestly exercised. The articles in database/seeders/data/ are proper
 * posts with real structure, and several of them document problems that came
 * up while this project was built.
 *
 * Cover photos live in database/seeders/assets/covers/ and are committed, so
 * seeding needs no network access and produces the same result every time.
 */
class DatabaseSeeder extends Seeder
{
    public function run(): void
    {
        $users = $this->seedUsers();
        $categories = $this->seedCategories();
        $tags = $this->seedTags();

        $posts = $this->seedArticles($users, $categories, $tags);

        $this->seedComments($posts, $users['admin']);
        $this->seedUnpublishedWork($users['admin'], $categories, $tags);

        $this->report();
    }

    /**
     * @return array{admin: User, author: User}
     */
    private function seedUsers(): array
    {
        // firstOrCreate so `db:seed` can be run twice without colliding with
        // the unique index on email.
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
                'name' => 'Sana Iqbal',
                'password' => 'password',
                'role' => UserRole::Author,
                'email_verified_at' => now(),
            ],
        );

        return ['admin' => $admin, 'author' => $author];
    }

    /**
     * @return Collection<string, Category>
     */
    private function seedCategories()
    {
        return collect([
            ['name' => 'Laravel', 'description' => 'Framework patterns, the reasoning behind them, and the traps that only show up in production.'],
            ['name' => 'Engineering', 'description' => 'Docker, deployment and the unglamorous decisions that keep software running.'],
            ['name' => 'Data', 'description' => 'PostgreSQL, query plans and getting the schema right the first time.'],
            ['name' => 'Career', 'description' => 'Reviews, debugging habits and working well with other engineers.'],
        ])->mapWithKeys(fn (array $data): array => [
            $data['name'] => Category::firstOrCreate(
                ['slug' => Str::slug($data['name'])],
                ['name' => $data['name'], 'description' => $data['description']],
            ),
        ]);
    }

    /**
     * @return Collection<string, Tag>
     */
    private function seedTags()
    {
        return collect([
            'php', 'eloquent', 'docker', 'postgres', 'testing',
            'performance', 'security', 'devops', 'queues',
        ])->mapWithKeys(fn (string $name): array => [
            $name => Tag::firstOrCreate(
                ['slug' => Str::slug($name)],
                ['name' => $name === 'php' ? 'PHP' : Str::title($name)],
            ),
        ]);
    }

    /**
     * @param  array{admin: User, author: User}  $users
     * @return Collection<string, Post>
     */
    private function seedArticles(array $users, $categories, $tags)
    {
        $articles = require database_path('seeders/data/articles.php');
        $posts = collect();

        foreach ($articles as $index => $article) {
            $slug = Str::slug($article['title']);

            $post = Post::withTrashed()->firstOrNew(['slug' => $slug]);

            $post->fill([
                'title' => $article['title'],
                'excerpt' => $article['excerpt'],
                // The heredocs in the data file are indented for readability;
                // strip that indentation so the Markdown parses correctly.
                'body' => $this->dedent($article['body']),
                'status' => PostStatus::Published,
                'published_at' => now()->subDays($article['days_ago'])->setTime(9, 30),
                'cover_image' => $this->storeCover($article['cover']),
            ]);

            // Alternate authorship so the "author sees only their own posts"
            // behaviour has something to show.
            $post->user()->associate($index % 4 === 3 ? $users['author'] : $users['admin']);
            $post->category()->associate($categories[$article['category']]);

            // A plausible spread, weighted towards older posts.
            $post->view_count = random_int(120, 400) + ($article['days_ago'] * random_int(4, 14));

            $post->save();

            $post->tags()->sync(
                collect($article['tags'])->map(fn (string $t) => $tags[$t]->id)->all(),
            );

            $posts[$post->slug] = $post;
        }

        return $posts;
    }

    private function seedComments($posts, User $admin): void
    {
        $threads = require database_path('seeders/data/comments.php');

        foreach ($threads as $slug => $comments) {
            $post = $posts[$slug] ?? null;

            if ($post === null) {
                continue;
            }

            foreach ($comments as $data) {
                $parent = $this->makeComment($post, $data, $admin);

                foreach ($data['replies'] ?? [] as $reply) {
                    $this->makeComment($post, $reply, $admin, $parent);
                }
            }
        }
    }

    /**
     * @param  array<string, mixed>  $data
     */
    private function makeComment(Post $post, array $data, User $admin, ?Comment $parent = null): Comment
    {
        $comment = Comment::firstOrNew([
            'post_id' => $post->id,
            'author_email' => $data['email'],
            'parent_id' => $parent?->id,
        ]);

        $comment->fill([
            'author_name' => $data['author'],
            'body' => $data['body'],
            'status' => CommentStatus::from($data['status'] ?? 'approved'),
        ]);

        // Replies from the blog owner are linked to the real account, so the
        // "author" badge shows on them.
        if (! empty($data['by_admin'])) {
            $comment->user()->associate($admin);
        }

        $comment->post()->associate($post);

        // Sit the conversation after the article, and replies after their parent.
        $comment->created_at = $parent
            ? $parent->created_at->addHours(random_int(2, 20))
            : $post->published_at->addHours(random_int(3, 72));
        $comment->updated_at = $comment->created_at;

        $comment->save();

        return $comment;
    }

    /**
     * A draft and a scheduled post, so the admin panel and the
     * posts:publish-scheduled command have something real to act on.
     */
    private function seedUnpublishedWork(User $admin, $categories, $tags): void
    {
        $draft = Post::withTrashed()->firstOrNew(['slug' => 'notes-on-livewire-4']);
        $draft->fill([
            'title' => 'Notes on Livewire 4 (work in progress)',
            'excerpt' => 'Half-finished thoughts on what changed and whether it is worth migrating.',
            'body' => $this->dedent(<<<'MD'
            Still reading through the upgrade notes. Rough impressions so far, in no order:

            ## What looks genuinely better

            - The new component syntax is less ceremony for simple cases.
            - Islands look like the right answer to the "one slow widget blocks the page" problem.

            ## What I need to check

            - How much of the Livewire 3 API survives untouched.
            - Whether the Alpine bundling story changes at all.

            **Do not publish until I have actually migrated something non-trivial.**
            MD),
            'status' => PostStatus::Draft,
            'published_at' => null,
            'view_count' => 0,
        ]);
        $draft->user()->associate($admin);
        $draft->category()->associate($categories['Laravel']);
        $draft->save();
        $draft->tags()->sync([$tags['php']->id]);

        $scheduled = Post::withTrashed()->firstOrNew(['slug' => 'a-year-of-postgres-in-production']);
        $scheduled->fill([
            'title' => 'A year of PostgreSQL in production',
            'excerpt' => 'What I would tell myself twelve months ago about indexes, connection limits and the things that actually broke.',
            'body' => $this->dedent(<<<'MD'
            Twelve months in, here is what mattered and what did not.

            ## The things that actually broke

            Connection limits, twice. Both times because a queue worker fleet scaled up faster
            than anyone had thought about `max_connections`.

            ## The things I worried about and should not have

            Table size. Thirty million rows is not a large table, and treating it like one led to
            a partitioning scheme nobody needed.

            *Full article scheduled for next week.*
            MD),
            'status' => PostStatus::Scheduled,
            // Deliberately in the future: the scheduler publishes it when the
            // time arrives, which is the whole point of the feature.
            'published_at' => now()->addDays(4)->setTime(9, 30),
            'view_count' => 0,
        ]);
        $scheduled->user()->associate($admin);
        $scheduled->category()->associate($categories['Data']);
        $scheduled->save();
        $scheduled->tags()->sync([$tags['postgres']->id, $tags['devops']->id]);
    }

    /**
     * Copy a committed cover photo onto the public disk.
     *
     * why copy rather than download: the images are in the repository, so
     * seeding works with no network access and gives identical results on
     * every machine and in CI.
     */
    private function storeCover(string $filename): ?string
    {
        $source = database_path('seeders/assets/covers/'.$filename);

        if (! is_file($source)) {
            return null;
        }

        $target = 'covers/'.$filename;

        if (! Storage::disk('public')->exists($target)) {
            Storage::disk('public')->put($target, (string) file_get_contents($source));
        }

        return $target;
    }

    /**
     * Remove the leading indentation a heredoc inherits from the code around
     * it. Without this, Markdown reads every line as an indented code block.
     */
    private function dedent(string $text): string
    {
        $lines = explode("\n", $text);

        $indent = collect($lines)
            ->filter(fn (string $line): bool => trim($line) !== '')
            ->map(fn (string $line): int => strlen($line) - strlen(ltrim($line)))
            ->min() ?? 0;

        return trim(implode("\n", array_map(
            fn (string $line): string => substr($line, $indent),
            $lines,
        )));
    }

    private function report(): void
    {
        $this->command->newLine();
        $this->command->info('Seeded '.Post::count().' posts, '.Comment::count().' comments.');
        $this->command->table(
            ['Role', 'Email', 'Password'],
            [
                ['Administrator', 'admin@example.com', 'password'],
                ['Author', 'author@example.com', 'password'],
            ],
        );
    }
}
