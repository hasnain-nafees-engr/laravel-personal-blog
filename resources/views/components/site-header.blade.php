{{-- Anonymous component: pure markup, no PHP class needed. --}}
<header class="sticky top-0 z-40 border-b border-paper-200/80 bg-paper-50/85 backdrop-blur
               dark:border-ink-800 dark:bg-ink-950/85">
    <nav class="mx-auto flex max-w-6xl items-center gap-4 px-4 py-4 sm:px-6"
         aria-label="Main navigation">

        <a href="{{ route('home') }}"
           class="group flex items-center gap-2.5 font-semibold tracking-tight">
            <span class="grid size-9 place-items-center rounded-xl bg-ink-900 font-serif text-lg
                         text-paper-50 transition group-hover:bg-ochre-600 dark:bg-ochre-600">
                H
            </span>
            <span class="hidden text-base sm:inline">{{ config('app.name') }}</span>
        </a>

        <div class="ml-auto flex items-center gap-1 sm:gap-2">
            <a href="{{ route('posts.index') }}"
               @class([
                   'rounded-lg px-3 py-2 text-sm font-medium transition hover:bg-paper-200 dark:hover:bg-ink-800',
                   'text-ochre-700 dark:text-ochre-300' => request()->routeIs('posts.*'),
               ])>
                {{ __('blog.all_posts') }}
            </a>

            {{-- Live search - the one place a page reload would be painful. --}}
            <livewire:post-search />

            <x-theme-toggle />

            @auth
                <a href="{{ route('admin.dashboard') }}"
                   class="rounded-lg bg-ink-900 px-3 py-2 text-sm font-medium text-paper-50
                          transition hover:bg-ink-700 dark:bg-ochre-600 dark:hover:bg-ochre-700">
                    Dashboard
                </a>
            @else
                <a href="{{ route('login') }}"
                   class="rounded-lg px-3 py-2 text-sm font-medium transition
                          hover:bg-paper-200 dark:hover:bg-ink-800">
                    Sign in
                </a>
            @endauth
        </div>
    </nav>
</header>
