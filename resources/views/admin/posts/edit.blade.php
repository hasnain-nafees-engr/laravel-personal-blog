<x-admin-layout :title="'Edit: '.$post->title">
    <div class="mb-5 flex flex-wrap items-center gap-3">
        <span class="rounded-full px-2.5 py-1 text-xs font-medium {{ $post->status->badgeClasses() }}">
            {{ $post->status->label() }}
        </span>

        @if ($post->isPublished())
            <a href="{{ route('posts.show', $post) }}" target="_blank" rel="noopener"
               class="text-sm text-ochre-700 hover:underline dark:text-ochre-300">View live &nearr;</a>
        @else
            <a href="{{ route('admin.posts.preview', $post) }}" target="_blank" rel="noopener"
               class="text-sm text-ochre-700 hover:underline dark:text-ochre-300">Preview draft &nearr;</a>
        @endif

        <span class="text-sm text-ink-400">
            {{ trans_choice('blog.views', $post->view_count, ['count' => number_format($post->view_count)]) }}
        </span>
    </div>

    @include('admin.posts.form', [
        'action' => route('admin.posts.update', $post),
        'method' => 'PUT',
    ])
</x-admin-layout>
