{{-- Anonymous component with a typed prop.
     Used by the home page, the index and both archive pages - one definition,
     four call sites, so a design change happens in exactly one file. --}}
@props(['post'])

<article class="group flex flex-col overflow-hidden rounded-2xl border border-paper-200 bg-white
                transition hover:-translate-y-0.5 hover:border-ochre-300 hover:shadow-lg
                dark:border-ink-800 dark:bg-ink-900 dark:hover:border-ochre-700">

    @if ($post->cover_image)
        <a href="{{ route('posts.show', $post) }}" tabindex="-1" aria-hidden="true">
            <img src="{{ Storage::url($post->cover_image) }}" alt=""
                 loading="lazy" class="aspect-[16/9] w-full object-cover">
        </a>
    @endif

    <div class="flex flex-1 flex-col p-5">
        <div class="flex flex-wrap items-center gap-2 text-xs">
            @if ($post->category)
                <a href="{{ route('categories.show', $post->category) }}"
                   class="rounded-full bg-ochre-100 px-2.5 py-1 font-medium text-ochre-700
                          transition hover:bg-ochre-300/50 dark:bg-ochre-700/20 dark:text-ochre-300">
                    {{ $post->category->name }}
                </a>
            @endif
            <span class="text-ink-400 dark:text-ink-300">
                {{ $post->published_at?->format('j M Y') }}
            </span>
        </div>

        <h3 class="mt-3 font-serif text-xl/snug font-semibold text-ink-900 dark:text-paper-50">
            <a href="{{ route('posts.show', $post) }}"
               class="transition group-hover:text-ochre-700 dark:group-hover:text-ochre-300">
                {{-- {{ }} escapes automatically: a title containing <script>
                     is printed as text, never executed. --}}
                {{ $post->title }}
            </a>
        </h3>

        <p class="mt-2 line-clamp-3 text-sm/6 text-ink-500 dark:text-paper-300">
            {{ $post->summary }}
        </p>

        <div class="mt-4 flex items-center gap-3 border-t border-paper-200 pt-3 text-xs
                    text-ink-400 dark:border-ink-800 dark:text-ink-300">
            <span>{{ $post->user->name }}</span>
            <span aria-hidden="true">&middot;</span>
            <span>{{ trans_choice('blog.minutes_read', $post->reading_time, ['count' => $post->reading_time]) }}</span>

            @isset($post->approved_comments_count)
                <span aria-hidden="true">&middot;</span>
                <span>{{ trans_choice('blog.comments', $post->approved_comments_count, ['count' => $post->approved_comments_count]) }}</span>
            @endisset
        </div>
    </div>
</article>
