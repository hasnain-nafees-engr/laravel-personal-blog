{{-- Toast for session('status') / session('error'), shown after every
     create, update or delete. Alpine handles the dismiss + auto-hide. --}}
@if (session('status') || session('error'))
    @php($isError = (bool) session('error'))

    <div
        x-data="{ show: true }"
        x-show="show"
        x-init="setTimeout(() => show = false, 6000)"
        x-transition:enter="transition ease-out duration-300"
        x-transition:enter-start="opacity-0 translate-y-3"
        x-transition:leave="transition ease-in duration-200"
        x-transition:leave-end="opacity-0 translate-y-3"
        role="status"
        aria-live="polite"
        class="fixed inset-x-4 bottom-4 z-50 mx-auto max-w-md sm:inset-x-auto sm:right-6"
    >
        <div @class([
            'flex items-start gap-3 rounded-xl border px-4 py-3 shadow-lg',
            'border-emerald-200 bg-emerald-50 text-emerald-900 dark:border-emerald-500/30 dark:bg-emerald-500/10 dark:text-emerald-200' => ! $isError,
            'border-rose-200 bg-rose-50 text-rose-900 dark:border-rose-500/30 dark:bg-rose-500/10 dark:text-rose-200' => $isError,
        ])>
            <svg class="mt-0.5 size-5 shrink-0" fill="none" viewBox="0 0 24 24" stroke-width="1.8"
                 stroke="currentColor" aria-hidden="true">
                @if ($isError)
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M12 9v3.75m9-.75a9 9 0 1 1-18 0 9 9 0 0 1 18 0Zm-9 3.75h.008v.008H12v-.008Z" />
                @else
                    <path stroke-linecap="round" stroke-linejoin="round"
                          d="M9 12.75 11.25 15 15 9.75M21 12a9 9 0 1 1-18 0 9 9 0 0 1 18 0Z" />
                @endif
            </svg>

            <p class="text-sm font-medium">{{ session('error') ?: session('status') }}</p>

            <button type="button" @click="show = false"
                    class="ml-auto -m-1 rounded p-1 hover:bg-black/5 dark:hover:bg-white/10"
                    aria-label="Dismiss">
                <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                     stroke="currentColor" aria-hidden="true">
                    <path stroke-linecap="round" stroke-linejoin="round" d="M6 18 18 6M6 6l12 12" />
                </svg>
            </button>
        </div>
    </div>
@endif
