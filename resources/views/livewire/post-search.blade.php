{{-- Livewire renders this on the server and swaps the HTML in as you type.
     Alpine (bundled with Livewire) handles only the open/close state. --}}
<div x-data="{ open: false }" @click.outside="open = false" @keydown.escape="open = false"
     class="relative">

    <label for="post-search" class="sr-only">Search articles</label>

    <input
        id="post-search"
        type="search"
        {{-- .live.debounce.400ms: send to the server 400ms after typing stops,
             not on every keystroke. Without the debounce, "laravel" would fire
             seven searches. --}}
        wire:model.live.debounce.400ms="term"
        @focus="open = true"
        placeholder="{{ __('blog.search_placeholder') }}"
        autocomplete="off"
        role="combobox"
        aria-expanded="false"
        :aria-expanded="open.toString()"
        aria-controls="search-results"
        class="w-36 rounded-lg border-paper-300 bg-paper-100 py-2 pl-9 text-sm transition
               focus:w-56 focus:border-ochre-500 focus:ring-ochre-500 sm:w-44 sm:focus:w-72
               dark:border-ink-700 dark:bg-ink-900"
    >

    <svg class="pointer-events-none absolute top-2.5 left-2.5 size-4 text-ink-400"
         fill="none" viewBox="0 0 24 24" stroke-width="2" stroke="currentColor" aria-hidden="true">
        <path stroke-linecap="round" stroke-linejoin="round"
              d="m21 21-5.197-5.197m0 0A7.5 7.5 0 1 0 5.196 5.196a7.5 7.5 0 0 0 10.607 10.607Z" />
    </svg>

    {{-- Loading state: Livewire toggles this automatically while a request
         to the server is in flight. --}}
    <div wire:loading wire:target="term"
         class="absolute top-2.5 right-2.5" aria-hidden="true">
        <svg class="size-4 animate-spin text-ochre-600" viewBox="0 0 24 24" fill="none">
            <circle class="opacity-25" cx="12" cy="12" r="10" stroke="currentColor" stroke-width="4"/>
            <path class="opacity-75" fill="currentColor"
                  d="M4 12a8 8 0 0 1 8-8V0C5.373 0 0 5.373 0 12h4z"/>
        </svg>
    </div>

    <div x-show="open && $wire.term.length > 0" x-cloak x-transition
         id="search-results" role="listbox"
         class="absolute right-0 z-50 mt-2 w-80 overflow-hidden rounded-xl border border-paper-200
                bg-white shadow-xl sm:w-96 dark:border-ink-800 dark:bg-ink-900">

        @if (! $hasTerm)
            <p class="px-4 py-6 text-center text-sm text-ink-400">
                {{ __('blog.search_hint') }}
            </p>
        @elseif ($results->isEmpty())
            {{-- Empty state --}}
            <p class="px-4 py-6 text-center text-sm text-ink-400">
                {{ __('blog.no_results', ['term' => $term]) }}
            </p>
        @else
            <ul class="max-h-96 divide-y divide-paper-200 overflow-y-auto dark:divide-ink-800">
                @foreach ($results as $result)
                    <li role="option" aria-selected="false">
                        <a href="{{ route('posts.show', $result) }}"
                           class="block px-4 py-3 transition hover:bg-paper-100 dark:hover:bg-ink-800">
                            <p class="font-medium text-ink-900 dark:text-paper-50">{{ $result->title }}</p>
                            <p class="mt-0.5 flex items-center gap-2 text-xs text-ink-400">
                                @if ($result->category)
                                    <span>{{ $result->category->name }}</span>
                                    <span aria-hidden="true">&middot;</span>
                                @endif
                                <span>{{ $result->published_at?->format('j M Y') }}</span>
                            </p>
                        </a>
                    </li>
                @endforeach
            </ul>
        @endif
    </div>
</div>
