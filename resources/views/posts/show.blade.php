<x-app-layout
    :title="$post->title"
    :description="$post->summary"
    :image="$post->cover_image"
    og-type="article"
    :published-at="$post->published_at"
>
    @if (! empty($isPreview))
        {{-- Draft preview banner - only the author or an admin can reach this. --}}
        <div class="bg-ochre-600 px-4 py-3 text-center text-sm font-medium text-white">
            {{ __('blog.draft_preview') }}
        </div>
    @endif

    <article class="mx-auto max-w-3xl px-4 py-12 sm:px-6">

        <header>
            <div class="flex flex-wrap items-center gap-2 text-sm">
                @if ($post->category)
                    <a href="{{ route('categories.show', $post->category) }}"
                       class="rounded-full bg-ochre-100 px-3 py-1 font-medium text-ochre-700
                              transition hover:bg-ochre-300/50 dark:bg-ochre-700/20 dark:text-ochre-300">
                        {{ $post->category->name }}
                    </a>
                @endif
                <span class="text-ink-400 dark:text-ink-300">
                    {{ __('blog.published_on', ['date' => $post->published_at?->format('j F Y') ?? '—']) }}
                </span>
            </div>

            <h1 class="mt-4 font-serif text-4xl/tight font-semibold text-ink-900 sm:text-5xl/tight
                       dark:text-paper-50">
                {{ $post->title }}
            </h1>

            <div class="mt-5 flex flex-wrap items-center gap-x-4 gap-y-2 border-y border-paper-200
                        py-4 text-sm text-ink-500 dark:border-ink-800 dark:text-paper-300">
                <span class="font-medium text-ink-700 dark:text-paper-100">
                    {{ __('blog.written_by', ['name' => $post->user->name]) }}
                </span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ trans_choice('blog.minutes_read', $post->reading_time, ['count' => $post->reading_time]) }}</span>
                <span aria-hidden="true">&middot;</span>
                <span>{{ trans_choice('blog.views', $post->view_count, ['count' => number_format($post->view_count)]) }}</span>
            </div>
        </header>

        @if ($post->cover_image)
            <img src="{{ Storage::url($post->cover_image) }}" alt=""
                 class="mt-8 aspect-[16/9] w-full rounded-2xl object-cover">
        @endif

        {{-- UNESCAPED OUTPUT, and the one place it is allowed.

             $post->body_html comes from CommonMarkRenderer, which parses the
             Markdown with html_input: 'strip' and allow_unsafe_links: false.
             Any <script>, <iframe>, onclick= or javascript: URL an author
             pastes is removed before this line ever sees it. Printing the raw
             `body` here with {!! !!} would be a stored-XSS hole; printing the
             rendered output is safe by construction. --}}
        <div class="prose-blog mt-10">
            {!! $post->body_html !!}
        </div>

        @if ($post->tags->isNotEmpty())
            <div class="mt-10 flex flex-wrap gap-2 border-t border-paper-200 pt-6 dark:border-ink-800">
                @foreach ($post->tags as $tag)
                    <a href="{{ route('tags.show', $tag) }}"
                       class="rounded-full border border-paper-300 px-3 py-1 text-sm text-ink-600
                              transition hover:border-ochre-500 hover:text-ochre-700
                              dark:border-ink-700 dark:text-paper-300 dark:hover:text-ochre-300">
                        #{{ $tag->name }}
                    </a>
                @endforeach
            </div>
        @endif
    </article>

    {{-- Related posts --}}
    @if ($related->isNotEmpty())
        <section class="mx-auto max-w-6xl px-4 py-10 sm:px-6" aria-labelledby="related-heading">
            <h2 id="related-heading" class="font-serif text-2xl font-semibold text-ink-900 dark:text-paper-50">
                {{ __('blog.related_posts') }}
            </h2>
            {{-- why $relatedPost and not $post: a @foreach variable leaks into
                 the surrounding scope in Blade, so reusing $post here would
                 overwrite the article being displayed and the comments
                 partial below would render the wrong post's thread. --}}
            <div class="mt-6 grid gap-6 sm:grid-cols-2 lg:grid-cols-3">
                @foreach ($related as $relatedPost)
                    <x-post-card :post="$relatedPost" />
                @endforeach
            </div>
        </section>
    @endif

    {{-- Comments.

         @include vs component: this is a plain partial that needs the whole
         parent scope ($post) and is used exactly once, so @include is the
         honest choice - no props to declare, nothing reusable to abstract.
         x-post-card above is a component because it IS reused, with a defined
         prop, in four different views. --}}
    @unless (! empty($isPreview))
        @include('posts.partials.comments')
    @endunless
</x-app-layout>
