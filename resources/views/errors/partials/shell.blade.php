{{-- Standalone error page.

     why NOT the normal layout: the 500 page must render when things are
     already broken. The site header runs a Livewire component and the footer
     reads config; if the failure was a database outage, reusing that layout
     would throw a second exception and the visitor would get a blank white
     screen instead of an apology. This page needs nothing but Vite. --}}
<!DOCTYPE html>
<html lang="{{ str_replace('_', '-', app()->getLocale()) }}">
<head>
    <meta charset="utf-8">
    <meta name="viewport" content="width=device-width, initial-scale=1">
    <title>{{ $code }} — {{ $title }}</title>
    @vite(['resources/css/app.css', 'resources/js/app.js'])
</head>
<body class="grid min-h-screen place-items-center px-4">
    <main class="max-w-md text-center">
        <p class="font-serif text-7xl font-semibold text-ochre-600">{{ $code }}</p>

        <h1 class="mt-4 font-serif text-2xl font-semibold text-ink-900 dark:text-paper-50">
            {{ $title }}
        </h1>

        <p class="mt-2 text-ink-500 dark:text-paper-300">{{ $message }}</p>

        <a href="{{ url('/') }}"
           class="mt-8 inline-flex items-center gap-1.5 rounded-lg bg-ink-900 px-4 py-2.5 text-sm
                  font-medium text-paper-50 transition hover:bg-ochre-600
                  dark:bg-ochre-600 dark:hover:bg-ochre-700">
            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                 stroke="currentColor" aria-hidden="true">
                <path stroke-linecap="round" stroke-linejoin="round" d="M10.5 19.5 3 12m0 0 7.5-7.5M3 12h18" />
            </svg>
            Back to the blog
        </a>
    </main>
</body>
</html>
