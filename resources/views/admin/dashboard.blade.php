<x-admin-layout title="Dashboard">
    {{-- Stat tiles --}}
    <div class="grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @php($tiles = [
            ['Published', $counts['published'], 'posts live on the site'],
            ['Drafts', $counts['drafts'], 'not visible yet'],
            ['Scheduled', $counts['scheduled'], 'queued to go live'],
            ['Pending comments', $counts['comments_pending'], 'waiting for review'],
        ])

        @foreach ($tiles as [$label, $value, $hint])
            <div class="rounded-2xl border border-paper-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm text-ink-500 dark:text-paper-300">{{ $label }}</p>
                <p class="mt-1 font-serif text-3xl font-semibold text-ink-900 dark:text-paper-50">
                    {{ number_format($value) }}
                </p>
                <p class="mt-1 text-xs text-ink-400">{{ $hint }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-4 grid gap-4 sm:grid-cols-2 lg:grid-cols-4">
        @foreach ([['Total views', $counts['views']], ['Comments', $counts['comments_total']], ['Categories', $counts['categories']], ['Tags', $counts['tags']]] as [$label, $value])
            <div class="rounded-2xl border border-paper-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
                <p class="text-sm text-ink-500 dark:text-paper-300">{{ $label }}</p>
                <p class="mt-1 font-serif text-2xl font-semibold">{{ number_format($value) }}</p>
            </div>
        @endforeach
    </div>

    <div class="mt-8 grid gap-6 lg:grid-cols-2">
        {{-- Moderation queue --}}
        <section class="rounded-2xl border border-paper-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <div class="flex items-baseline justify-between">
                <h2 class="font-serif text-lg font-semibold">Awaiting moderation</h2>
                <a href="{{ route('admin.comments.index') }}"
                   class="text-sm text-ochre-700 hover:underline dark:text-ochre-300">See all</a>
            </div>

            @forelse ($pendingComments as $comment)
                <div class="mt-4 border-t border-paper-200 pt-3 first:border-0 dark:border-ink-800">
                    <p class="text-sm font-medium">{{ $comment->author_name }}</p>
                    <p class="mt-0.5 line-clamp-2 text-sm text-ink-500 dark:text-paper-300">{{ $comment->body }}</p>
                    <p class="mt-1 text-xs text-ink-400">on {{ $comment->post?->title }}</p>
                </div>
            @empty
                <p class="mt-4 text-sm text-ink-400">Nothing waiting. Inbox zero.</p>
            @endforelse
        </section>

        {{-- Activity feed - powered by the polymorphic activity_logs table --}}
        <section class="rounded-2xl border border-paper-200 bg-white p-5 dark:border-ink-800 dark:bg-ink-900">
            <h2 class="font-serif text-lg font-semibold">Recent activity</h2>

            @forelse ($activity as $entry)
                <div class="mt-3 flex items-start gap-3 border-t border-paper-200 pt-3 first:border-0
                            dark:border-ink-800">
                    <span class="mt-1 size-2 shrink-0 rounded-full bg-ochre-500"></span>
                    <div class="min-w-0">
                        <p class="text-sm">
                            <span class="font-medium">{{ $entry->user?->name ?? 'System' }}</span>
                            <span class="text-ink-500 dark:text-paper-300">{{ str_replace('.', ' ', $entry->action) }}</span>
                        </p>
                        <p class="truncate text-xs text-ink-400">
                            {{ class_basename($entry->subject_type) }}:
                            {{ $entry->subject?->title ?? $entry->subject?->author_name ?? '#'.$entry->subject_id }}
                        </p>
                    </div>
                    <time class="ml-auto shrink-0 text-xs text-ink-400">{{ $entry->created_at?->diffForHumans() }}</time>
                </div>
            @empty
                <p class="mt-4 text-sm text-ink-400">No activity recorded yet.</p>
            @endforelse
        </section>
    </div>
</x-admin-layout>
