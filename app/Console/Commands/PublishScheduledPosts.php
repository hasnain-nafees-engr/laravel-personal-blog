<?php

declare(strict_types=1);

namespace App\Console\Commands;

use App\Enums\PostStatus;
use App\Models\Post;
use Illuminate\Console\Command;

/**
 * Publishes posts whose scheduled time has arrived.
 *
 * The `scheduler` container runs `php artisan schedule:work`, which calls
 * this every minute (see routes/console.php).
 *
 * why a command and not a check at read time: the public scope already hides
 * future posts, but the status column would stay "scheduled" forever, the
 * PostPublished event would never fire, and nothing would clear the caches
 * or write the audit line. This is the moment publication actually happens.
 */
class PublishScheduledPosts extends Command
{
    /** The signature defines the command name, arguments and options. */
    protected $signature = 'posts:publish-scheduled
                            {--dry-run : List what would be published without changing anything}';

    protected $description = 'Publish scheduled posts whose publish time has passed';

    public function handle(): int
    {
        $due = Post::query()
            ->scheduled()
            ->where('published_at', '<=', now())
            ->get();

        if ($due->isEmpty()) {
            $this->components->info('No scheduled posts are due.');

            return self::SUCCESS;
        }

        foreach ($due as $post) {
            if ($this->option('dry-run')) {
                $this->components->twoColumnDetail($post->title, 'would publish');

                continue;
            }

            // Saving triggers PostObserver, which fires PostPublished, which
            // clears the caches and writes the activity log.
            $post->update(['status' => PostStatus::Published]);

            $this->components->twoColumnDetail($post->title, '<fg=green>published</>');
        }

        $this->newLine();
        $this->components->info(sprintf(
            '%d post(s) %s.',
            $due->count(),
            $this->option('dry-run') ? 'would be published' : 'published',
        ));

        return self::SUCCESS;
    }
}
