<x-admin-layout title="Tags">
    <div class="flex items-center justify-between">
        <p class="text-sm text-ink-500 dark:text-paper-300">
            A post can carry several tags. Deleting a tag only removes the link, never the posts.
        </p>
        <a href="{{ route('admin.tags.create') }}"
           class="rounded-lg bg-ink-900 px-4 py-2 text-sm font-medium text-paper-50
                  transition hover:bg-ochre-600 dark:bg-ochre-600 dark:hover:bg-ochre-700">
            New tag
        </a>
    </div>

    @if ($tags->isEmpty())
        <div class="mt-6"><x-empty-state title="No tags yet" message="Tags help readers find related articles." /></div>
    @else
        <div class="mt-6 overflow-x-auto rounded-2xl border border-paper-200 bg-white
                    dark:border-ink-800 dark:bg-ink-900">
            <table class="w-full text-sm">
                <thead class="border-b border-paper-200 text-left text-xs text-ink-500 uppercase
                              dark:border-ink-800 dark:text-paper-300">
                    <tr>
                        <th scope="col" class="px-4 py-3 font-medium">Name</th>
                        <th scope="col" class="px-4 py-3 font-medium">Slug</th>
                        <th scope="col" class="px-4 py-3 font-medium">Posts</th>
                        <th scope="col" class="px-4 py-3 text-right font-medium">Actions</th>
                    </tr>
                </thead>
                <tbody class="divide-y divide-paper-200 dark:divide-ink-800">
                    @foreach ($tags as $tag)
                        <tr class="hover:bg-paper-50 dark:hover:bg-ink-800/50">
                            <td class="px-4 py-3 font-medium">{{ $tag->name }}</td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-400">{{ $tag->slug }}</td>
                            <td class="px-4 py-3">{{ $tag->posts_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('tags.show', $tag) }}" target="_blank" rel="noopener"
                                       class="rounded-lg px-2.5 py-1.5 hover:bg-paper-200 dark:hover:bg-ink-800">View</a>
                                    <a href="{{ route('admin.tags.edit', $tag) }}"
                                       class="rounded-lg px-2.5 py-1.5 hover:bg-paper-200 dark:hover:bg-ink-800">Edit</a>
                                    <x-confirm-delete
                                        :action="route('admin.tags.destroy', $tag)"
                                        title="Delete this tag?"
                                        message="It will be removed from every post that carries it. The posts are kept."
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $tags->links() }}</div>
    @endif
</x-admin-layout>
