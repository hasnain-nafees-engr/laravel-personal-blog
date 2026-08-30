<x-admin-layout title="Posts">
    <div class="flex flex-wrap items-center gap-3">
        {{-- Filters: a plain GET form, so the state lives in the URL and is
             shareable and bookmarkable. --}}
        <form method="GET" action="{{ route('admin.posts.index') }}" class="flex flex-wrap gap-2">
            <label for="q" class="sr-only">Search posts</label>
            <input type="search" id="q" name="q" value="{{ request('q') }}" placeholder="Search title…"
                   class="rounded-lg border-paper-300 bg-white text-sm focus:border-ochre-500
                          focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">

            <label for="status" class="sr-only">Filter by status</label>
            <select id="status" name="status"
                    class="rounded-lg border-paper-300 bg-white text-sm focus:border-ochre-500
                           focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
                <option value="">All statuses</option>
                @foreach ($statuses as $status)
                    <option value="{{ $status->value }}" @selected(request('status') === $status->value)>
                        {{ $status->label() }}
                    </option>
                @endforeach
            </select>

            <button type="submit"
                    class="rounded-lg border border-paper-300 px-3 py-2 text-sm font-medium
                           hover:bg-paper-100 dark:border-ink-700 dark:hover:bg-ink-800">
                Filter
            </button>
        </form>

        <a href="{{ route('admin.posts.create') }}"
           class="ml-auto rounded-lg bg-ink-900 px-4 py-2 text-sm font-medium text-paper-50
                  transition hover:bg-ochre-600 dark:bg-ochre-600 dark:hover:bg-ochre-700">
            New post
        </a>
    </div>

    @if ($posts->isEmpty())
        <div class="mt-6">
            <x-empty-state title="No posts found" message="Try clearing the filters, or write your first article." />
        </div>
    @else
        <div class="mt-6 overflow-x-auto rounded-2xl border border-paper-200 bg-white
                    dark:border-ink-800 dark:bg-ink-900">
            <table class="w-full text-sm">
                <caption class="sr-only">Posts, newest first</caption>
                <thead class="border-b border-paper-200 text-left text-xs text-ink-500
                              uppercase dark:border-ink-800 dark:text-paper-300">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Title</th>
                        <th scope="col" class="px-4 py-3 font-medium">Status</th>
                        <th scope="col" class="px-4 py-3 font-medium">Category</th>
                        <th scope="col" class="px-4 py-3 font-medium">Comments</th>
                        <th scope="col" class="px-4 py-3 font-medium">Updated</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200 dark:divide-ink-800">
                    @foreach ($posts as $post)
                        <tr class="hover:bg-paper-50 dark:hover:bg-ink-800/50">
                            <td class="px-4 py-3">
                                <a href="{{ route('admin.posts.edit', $post) }}"
                                   class="font-medium hover:text-ochre-700 dark:hover:text-ochre-300">
                                    {{ $post->title }}
                                </a>
                                <p class="text-xs text-ink-400">{{ $post->user->name }}</p>
                            </td>
                            <td class="px-4 py-3">
                                <span class="rounded-full px-2 py-1 text-xs font-medium {{ $post->status->badgeClasses() }}">
                                    {{ $post->status->label() }}
                                </span>
                            </td>
                            <td class="px-4 py-3 text-ink-500 dark:text-paper-300">
                                {{ $post->category?->name ?? '—' }}
                            </td>
                            <td class="px-4 py-3 text-ink-500 dark:text-paper-300">{{ $post->comments_count }}</td>
                            <td class="px-4 py-3 text-ink-400">{{ $post->updated_at->diffForHumans() }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    @if ($post->isPublished())
                                        <a href="{{ route('posts.show', $post) }}" target="_blank" rel="noopener"
                                           class="rounded-lg px-2.5 py-1.5 text-sm hover:bg-paper-200 dark:hover:bg-ink-800">
                                            View
                                        </a>
                                    @else
                                        <a href="{{ route('admin.posts.preview', $post) }}" target="_blank" rel="noopener"
                                           class="rounded-lg px-2.5 py-1.5 text-sm hover:bg-paper-200 dark:hover:bg-ink-800">
                                            Preview
                                        </a>
                                    @endif

                                    <a href="{{ route('admin.posts.edit', $post) }}"
                                       class="rounded-lg px-2.5 py-1.5 text-sm hover:bg-paper-200 dark:hover:bg-ink-800">
                                        Edit
                                    </a>

                                    {{-- @can checks PostPolicy::delete - the button is not
                                         even rendered for someone who may not use it. --}}
                                    @can('delete', $post)
                                        <x-confirm-delete
                                            :action="route('admin.posts.destroy', $post)"
                                            title="Move this post to the trash?"
                                            message="“{{ Str::limit($post->title, 60) }}” will stop being public. You can restore it later."
                                        />
                                    @endcan
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $posts->links() }}</div>
    @endif
</x-admin-layout>
