{{-- Public site layout. Receives page content through the $slot variable. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <meta name="csrf-token" content="{{ csrf_token() }}">

    {{-- A class-based component: it builds every meta/OG tag from props.
         See app/View/Components/SeoMeta.php --}}
    <x-seo-meta
        :title="$title ?? null"
        :description="$description ?? null"
        :image="$image ?? null"
        :type="$ogType ?? 'website'"
        :published="$publishedAt ?? null"
    />

    <link rel="alternate" type="application/rss+xml"
          title="{{ config('app.name') }}" href="{{ route('feed') }}">

    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="min-h-screen flex flex-col">
    {{-- Skip link: the first thing a keyboard user tabs to. --}}
    <a href="#main"
       class="sr-only focus:not-sr-only focus:absolute focus:top-4 focus:left-4 focus:z-50
              focus:rounded-lg focus:bg-ink-900 focus:px-4 focus:py-2 focus:text-paper-50">
        Skip to content
    </a>

    <x-site-header />

    <main id="main" class="flex-1">
        {{ $slot }}
    </main>

    <x-site-footer />

    {{-- Flash messages from redirect()->with('status', ...) --}}
    <x-flash />
</body>
</html>
