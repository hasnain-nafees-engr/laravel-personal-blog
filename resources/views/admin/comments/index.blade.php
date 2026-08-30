<x-admin-layout title="Comments">
    {{-- Status filter tabs --}}
    <nav class="flex flex-wrap gap-1 border-b border-paper-200 dark:border-ink-800" aria-label="Filter comments">
        @foreach (array_merge($statuses, ['all']) as $status)
            @php($value = is_string($status) ? 'all' : $status->value)
            @php($label = is_string($status) ? 'All' : $status->label())

            <a href="{{ route('admin.comments.index', ['status' => $value]) }}"
               @class([
                   '-mb-px border-b-2 px-4 py-2.5 text-sm font-medium transition',
                   'border-ochre-600 text-ochre-700 dark:text-ochre-300' => $currentStatus === $value,
                   'border-transparent text-ink-500 hover:text-ink-900 dark:text-paper-300' => $currentStatus !== $value,
               ])
               @if ($currentStatus === $value) aria-current="page" @endif>
                {{ $label }}
            </a>
        @endforeach
    </nav>

    @if ($comments->isEmpty())
        <div class="mt-6">
            <x-empty-state title="Nothing here" message="No comments with this status." />
        </div>
    @else
        <ul class="mt-6 space-y-3">
            @foreach ($comments as $comment)
                <li class="rounded-2xl border border-paper-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
                    <div class="flex flex-wrap items-start gap-3">
                        <div class="grid size-9 shrink-0 place-items-center rounded-full bg-ochre-100
                                    text-sm font-semibold text-ochre-700
                                    dark:bg-ochre-700/20 dark:text-ochre-300" aria-hidden="true">
                            {{ $comment->initials }}
                        </div>

                        <div class="min-w-0 flex-1">
                            <div class="flex flex-wrap items-center gap-2">
                                <span class="font-medium">{{ $comment->author_name }}</span>
                                <span class="text-xs text-ink-400">{{ $comment->author_email }}</span>
                                <span class="rounded-full px-2 py-0.5 text-xs font-medium {{ $comment->status->badgeClasses() }}">
                                    {{ $comment->status->label() }}
                                </span>
                                @if ($comment->parent_id)
                                    <span class="text-xs text-ink-400">(reply)</span>
                                @endif
                            </div>

                            <p class="mt-2 text-sm/6 whitespace-pre-line">{{ $comment->body }}</p>

                            <p class="mt-2 text-xs text-ink-400">
                                on
                                <a href="{{ route('posts.show', $comment->post) }}" target="_blank" rel="noopener"
                                   class="hover:underline">{{ $comment->post?->title }}</a>
                                · {{ $comment->created_at->diffForHumans() }}
                            </p>
                        </div>

                        <div class="flex shrink-0 items-center gap-1">
                            @can('moderate', $comment)
                                @if ($comment->status !== \App\Enums\CommentStatus::Approved)
                                    <form method="POST" action="{{ route('admin.comments.approve', $comment) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-emerald-700
                                                       hover:bg-emerald-50 dark:text-emerald-300 dark:hover:bg-emerald-500/10">
                                            Approve
                                        </button>
                                    </form>
                                @endif

                                @if ($comment->status !== \App\Enums\CommentStatus::Rejected)
                                    <form method="POST" action="{{ route('admin.comments.reject', $comment) }}">
                                        @csrf @method('PATCH')
                                        <button type="submit"
                                                class="rounded-lg px-2.5 py-1.5 text-sm font-medium text-ink-600
                                                       hover:bg-paper-200 dark:text-paper-300 dark:hover:bg-ink-800">
                                            Reject
                                        </button>
                                    </form>
                                @endif
                            @endcan

                            @can('delete', $comment)
                                <x-confirm-delete
                                    :action="route('admin.comments.destroy', $comment)"
                                    title="Delete this comment?"
                                    message="Any replies to it are deleted too. This cannot be undone."
                                />
                            @endcan
                        </div>
                    </div>
                </li>
            @endforeach
        </ul>

        <div class="mt-6">{{ $comments->links() }}</div>
    @endif
</x-admin-layout>
