<x-app-layout>
    <div class="mx-auto max-w-6xl px-4 py-12 sm:px-6 lg:py-16">

        {{-- Masthead --}}
        <section class="max-w-3xl">
            <p class="text-sm font-medium tracking-wide text-ochre-700 uppercase dark:text-ochre-300">
                {{ config('app.name') }}
            </p>
            <h1 class="mt-3 font-serif text-4xl/tight font-semibold text-ink-900 sm:text-5xl/tight
                       dark:text-paper-50">
                Notes on engineering,<br class="hidden sm:inline">
                written while building.
            </h1>
            <p class="mt-4 text-lg/8 text-ink-500 dark:text-paper-300">
                Long-form posts about Laravel, data pipelines and the unglamorous
                decisions that keep software running.
            </p>
        </section>

        @if ($featured)
            {{-- Featured article gets the wide treatment. --}}
            <section class="mt-14" aria-labelledby="featured-heading">
                <h2 id="featured-heading" class="sr-only">Featured article</h2>

                <article class="group grid gap-8 rounded-3xl border border-paper-200 bg-white p-6
                                transition hover:border-ochre-300 hover:shadow-xl sm:p-8
                                lg:grid-cols-2 lg:items-center dark:border-ink-800 dark:bg-ink-900">
                    <div class="order-2 lg:order-1">
                        <div class="flex flex-wrap items-center gap-2 text-xs">
                            <span class="rounded-full bg-ink-900 px-2.5 py-1 font-medium text-paper-50
                                         dark:bg-ochre-600">Latest</span>
                            @if ($featured->category)
                                <a href="{{ route('categories.show', $featured->category) }}"
                                   class="rounded-full bg-ochre-100 px-2.5 py-1 font-medium text-ochre-700
                                          dark:bg-ochre-700/20 dark:text-ochre-300">
                                    {{ $featured->category->name }}
                                </a>
                            @endif
                            <span class="text-ink-400 dark:text-ink-300">
                                {{ $featured->published_at?->format('j F Y') }}
                            </span>
                        </div>

                        <h3 class="mt-4 font-serif text-3xl/tight font-semibold text-ink-900 dark:text-paper-50">
                            <a href="{{ route('posts.show', $featured) }}"
                               class="transition group-hover:text-ochre-700 dark:group-hover:text-ochre-300">
                                {{ $featured->title }}
                            </a>
                        </h3>

                        <p class="mt-3 text-base/7 text-ink-500 dark:text-paper-300">
                            {{ $featured->summary }}
                        </p>

                        <div class="mt-5 flex items-center gap-3 text-sm text-ink-400 dark:text-ink-300">
                            <span>{{ $featured->user->name }}</span>
                            <span aria-hidden="true">&middot;</span>
                            <span>{{ trans_choice('blog.minutes_read', $featured->reading_time, ['count' => $featured->reading_time]) }}</span>
                        </div>

                        <a href="{{ route('posts.show', $featured) }}"
                           class="mt-6 inline-flex items-center gap-1.5 rounded-lg bg-ink-900 px-4 py-2.5
                                  text-sm font-medium text-paper-50 transition hover:bg-ochre-600
                                  dark:bg-ochre-600 dark:hover:bg-ochre-700">
                            {{ __('blog.read_more') }}
                            <svg class="size-4" fill="none" viewBox="0 0 24 24" stroke-width="2"
                                 stroke="currentColor" aria-hidden="true">
                                <path stroke-linecap="round" stroke-linejoin="round" d="M13.5 4.5 21 12m0 0-7.5 7.5M21 12H3" />
                            </svg>
                        </a>
                    </div>

                    <div class="order-1 lg:order-2">
                        @if ($featured->cover_image)
                            <img src="{{ Storage::url($featured->cover_image) }}" alt=""
                                 class="aspect-[4/3] w-full rounded-2xl object-cover">
                        @else
                            {{-- No cover: a typographic placeholder rather than a grey box. --}}
                            <div class="grid aspect-[4/3] w-full place-items-center rounded-2xl
                                        bg-gradient-to-br from-ink-900 to-ochre-700 p-8">
                                <span class="text-center font-serif text-2xl/snug text-paper-50/90">
                                    {{ Str::words($featured->title, 6, '…') }}
                                </span>
                            </div>
                        @endif
                    </div>
                </article>
            </section>
        @endif

        {{-- Recent posts grid --}}
        @if ($posts->isNotEmpty())
            <section class="mt-16" aria-labelledby="recent-heading">
                <div class="flex items-baseline justify-between">
                    <h2 id="recent-heading" class="font-serif text-2xl font-semibold text-ink-900 dark:text-paper-50">
                        Recent posts
                    </h2>
                    <a href="{{ route('posts.index') }}"
                       class="text-sm font-medium text-ochre-700 hover:underline dark:text-ochre-300">
                        {{ __('blog.all_posts') }} &rarr;
                    </a>
                </div>

                <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                    @foreach ($posts as $post)
                        <x-post-card :$post />
                    @endforeach
                </div>
            </section>
        @elseif (! $featured)
            <div class="mt-14">
                <x-empty-state :message="__('blog.no_posts_yet')" title="No articles yet" />
            </div>
        @endif
    </div>
</x-app-layout>
