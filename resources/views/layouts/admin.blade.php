{{-- Admin panel layout: sidebar + content, deliberately plainer than the
     public site so the two are never confused. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">
    <meta name="robots" content="noindex, nofollow">
    <title>{{ $title }} — {{ config('app.name') }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen bg-paper-100 dark:bg-ink-950">
<div x-data="{ sidebar: false }" class="flex min-h-screen">

    {{-- Sidebar --}}
    <aside
        class="fixed inset-y-0 left-0 z-40 w-64 -translate-x-full border-r border-paper-200 bg-white
               p-4 transition-transform lg:translate-x-0 dark:border-ink-800 dark:bg-ink-900"
        :class="sidebar && 'translate-x-0'"
    >
        <a href="{{ route('home') }}" class="flex items-center gap-2.5 px-2 py-2 font-semibold">
            <span class="grid size-8 place-items-center rounded-lg bg-ink-900 font-serif
                         text-paper-50 dark:bg-ochre-600">H</span>
            <span class="text-sm">{{ config('app.name') }}</span>
        </a>

        <nav class="mt-6 space-y-1 text-sm" aria-label="Admin navigation">
            @php($nav = [
                ['admin.dashboard',       'Dashboard',  'admin.dashboard'],
                ['admin.posts.index',     'Posts',      'admin.posts.*'],
                ['admin.categories.index','Categories', 'admin.categories.*'],
                ['admin.tags.index',      'Tags',       'admin.tags.*'],
                ['admin.comments.index',  'Comments',   'admin.comments.*'],
            ])

            @foreach ($nav as [$route, $label, $pattern])
                @continue(in_array($label, ['Categories', 'Tags'], true) && ! auth()->user()->isAdmin())
                <a href="{{ route($route) }}"
                   @class([
                       'flex items-center justify-between rounded-lg px-3 py-2 font-medium transition',
                       'bg-ochre-100 text-ochre-700 dark:bg-ochre-700/20 dark:text-ochre-300' => request()->routeIs($pattern),
                       'hover:bg-paper-100 dark:hover:bg-ink-800' => ! request()->routeIs($pattern),
                   ])>
                    {{ $label }}

                    @if ($label === 'Comments' && $pendingComments > 0)
                        <span class="rounded-full bg-rose-600 px-1.5 py-0.5 text-[10px] font-semibold text-white">
                            {{ $pendingComments }}
                        </span>
                    @endif
                </a>
            @endforeach
        </nav>

        <div class="absolute inset-x-4 bottom-4 border-t border-paper-200 pt-4 dark:border-ink-800">
            <p class="px-3 text-xs text-ink-400">Signed in as</p>
            <p class="px-3 text-sm font-medium">{{ auth()->user()->name }}</p>

            <div class="mt-2 flex items-center gap-1">
                <a href="{{ route('profile.edit') }}"
                   class="rounded-lg px-3 py-1.5 text-sm hover:bg-paper-100 dark:hover:bg-ink-800">
                    Profile
                </a>
                <form method="POST" action="{{ route('logout') }}">
                    @csrf
                    <button type="submit"
                            class="rounded-lg px-3 py-1.5 text-sm hover:bg-paper-100 dark:hover:bg-ink-800">
                        Log out
                    </button>
                </form>
                <div class="ml-auto"><x-theme-toggle /></div>
            </div>
        </div>
    </aside>

    {{-- Content --}}
    <div class="flex-1 lg:ml-64">
        <header class="flex items-center gap-3 border-b border-paper-200 bg-white px-4 py-3
                       lg:px-8 dark:border-ink-800 dark:bg-ink-900">
            <button type="button" @click="sidebar = !sidebar"
                    class="rounded-lg p-2 hover:bg-paper-100 lg:hidden dark:hover:bg-ink-800"
                    aria-label="Toggle navigation">
                <svg class="size-5" fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M3.75 6.75h16.5M3.75 12h16.5m-16.5 5.25h16.5" />
                </svg>
            </button>

            <h1 class="font-serif text-lg font-semibold">{{ $header ?? $title }}</h1>

            <a href="{{ route('home') }}" target="_blank" rel="noopener"
               class="ml-auto text-sm text-ink-500 hover:text-ochre-700 dark:text-paper-300">
                View site &nearr;
            </a>
        </header>

        <main class="p-4 lg:p-8">
            {{ $slot }}
        </main>
    </div>
</div>

<x-flash />
</body>
</html>
