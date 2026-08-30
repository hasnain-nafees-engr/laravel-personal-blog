<footer class="mt-20 border-t border-paper-200 bg-paper-100 dark:border-ink-800 dark:bg-ink-900">
    <div class="mx-auto max-w-6xl px-4 py-10 sm:px-6">
        <div class="flex flex-col gap-6 sm:flex-row sm:items-center sm:justify-between">
            <div>
                <p class="font-serif text-lg text-ink-900 dark:text-paper-100">
                    {{ config('app.name') }}
                </p>
                <p class="mt-1 text-sm text-ink-500 dark:text-paper-300">
                    Notes on engineering, Laravel and building things that last.
                </p>
            </div>

            <nav class="flex flex-wrap gap-x-5 gap-y-2 text-sm" aria-label="Footer">
                <a href="{{ route('posts.index') }}" class="hover:text-ochre-700 dark:hover:text-ochre-300">
                    {{ __('blog.all_posts') }}
                </a>
                <a href="{{ route('feed') }}" class="hover:text-ochre-700 dark:hover:text-ochre-300">RSS</a>
                <a href="{{ route('sitemap') }}" class="hover:text-ochre-700 dark:hover:text-ochre-300">Sitemap</a>
            </nav>
        </div>

        <p class="mt-8 text-xs text-ink-400 dark:text-ink-300">
            &copy; {{ now()->year }} {{ config('app.name') }}. Built with Laravel {{ Illuminate\Foundation\Application::VERSION }}.
        </p>
    </div>
</footer>
