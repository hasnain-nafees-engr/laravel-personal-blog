{{-- A single comment bubble. $comment and $isReply come from the includer. --}}
<div class="flex gap-3">
    <div class="grid size-9 shrink-0 place-items-center rounded-full bg-ochre-100 text-sm
                font-semibold text-ochre-700 dark:bg-ochre-700/20 dark:text-ochre-300"
         aria-hidden="true">
        {{ $comment->initials }}
    </div>

    <div class="min-w-0 flex-1">
        <div class="flex flex-wrap items-baseline gap-x-2">
            {{-- Escaped: a commenter called <script>alert(1)</script> shows up
                 as that literal text, which is exactly what we want. --}}
            <span class="text-sm font-semibold text-ink-900 dark:text-paper-50">
                {{ $comment->author_name }}
            </span>

            @if ($comment->user_id)
                <span class="rounded-full bg-ink-900 px-1.5 py-0.5 text-[10px] font-medium
                             text-paper-50 dark:bg-ochre-600">author</span>
            @endif

            <time datetime="{{ $comment->created_at->toIso8601String() }}"
                  class="text-xs text-ink-400 dark:text-ink-300">
                {{ $comment->created_at->diffForHumans() }}
            </time>
        </div>

        <p class="mt-1 text-sm/6 whitespace-pre-line text-ink-700 dark:text-paper-200">{{ $comment->body }}</p>

        @unless ($isReply)
            <button type="button"
                    @click="replyTo = {{ $comment->id }}; replyName = @js($comment->author_name); document.getElementById('body')?.focus()"
                    class="mt-1.5 text-xs font-medium text-ochre-700 hover:underline dark:text-ochre-300">
                {{ __('blog.reply') }}
            </button>
        @endunless
    </div>
</div>
