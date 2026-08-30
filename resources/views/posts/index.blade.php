{{-- Shared by /posts, /categories/{slug} and /tags/{slug}: the only
     difference is the heading and the empty message the controller passes. --}}
<x-app-layout :title="$heading" :description="$description ?? null">
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6">

        <header class="max-w-3xl">
            <h1 class="font-serif text-3xl/tight font-semibold text-ink-900 sm:text-4xl dark:text-paper-50">
                {{ $heading }}
            </h1>
            @if (! empty($description))
                <p class="mt-3 text-lg/8 text-ink-500 dark:text-paper-300">{{ $description }}</p>
            @endif
            <p class="mt-2 text-sm text-ink-400 dark:text-ink-300">
                {{ trans_choice('{0} No articles|{1} :count article|[2,*] :count articles', $posts->total(), ['count' => $posts->total()]) }}
            </p>
        </header>

        @if ($posts->isEmpty())
            <div class="mt-10">
                <x-empty-state :message="$emptyMessage ?? __('blog.no_posts_yet')" title="Nothing to read here yet" />
            </div>
        @else
            <div class="mt-10 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($posts as $post)
                    <x-post-card :$post />
                @endforeach
            </div>

            {{-- Laravel's paginator renders accessible prev/next links and
                 keeps the current query string (?q=, ?page=). --}}
            <div class="mt-10">
                {{ $posts->links() }}
            </div>
        @endif
    </div>
</x-app-layout>
