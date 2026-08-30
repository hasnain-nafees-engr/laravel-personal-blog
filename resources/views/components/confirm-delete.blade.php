{{-- Confirmation modal for destructive actions.

     why a modal instead of a plain submit: a delete cannot be undone by
     pressing Back. The form is a real <form method="POST"> with @method('DELETE')
     and a CSRF token, so it still works exactly like a normal Laravel delete -
     Alpine only decides when it is shown. --}}
@props([
    'action',
    'label' => 'Delete',
    'title' => 'Are you sure?',
    'message' => 'This action cannot be undone.',
    'method' => 'DELETE',
])

<div x-data="{ open: false }" @keydown.escape.window="open = false" class="inline">
    <button type="button" @click="open = true"
            class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-rose-700 transition
                   hover:bg-rose-50 dark:text-rose-300 dark:hover:bg-rose-500/10">
        {{ $label }}
    </button>

    <template x-teleport="body">
        <div x-show="open" x-cloak class="fixed inset-0 z-50 grid place-items-center p-4"
             role="dialog" aria-modal="true">

            <div x-show="open" x-transition.opacity @click="open = false"
                 class="absolute inset-0 bg-ink-950/50 backdrop-blur-sm"></div>

            <div x-show="open" x-transition
                 class="relative w-full max-w-md rounded-2xl border border-paper-200 bg-white p-6
                        shadow-xl dark:border-ink-800 dark:bg-ink-900">
                <h2 class="font-serif text-lg font-semibold text-ink-900 dark:text-paper-50">
                    {{ $title }}
                </h2>
                <p class="mt-2 text-sm text-ink-500 dark:text-paper-300">{{ $message }}</p>

                <div class="mt-6 flex justify-end gap-2">
                    <button type="button" @click="open = false"
                            class="rounded-lg px-4 py-2 text-sm font-medium transition
                                   hover:bg-paper-200 dark:hover:bg-ink-800">
                        Cancel
                    </button>

                    <form method="POST" action="{{ $action }}">
                        @csrf
                        @method($method)
                        <button type="submit"
                                class="rounded-lg bg-rose-600 px-4 py-2 text-sm font-medium text-white
                                       transition hover:bg-rose-700">
                            {{ $label }}
                        </button>
                    </form>
                </div>
            </div>
        </div>
    </template>
</div>
