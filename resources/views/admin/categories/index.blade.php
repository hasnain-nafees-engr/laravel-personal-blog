<x-admin-layout title="Categories">
    <div class="flex items-center justify-between">
        <p class="text-sm text-ink-500 dark:text-paper-300">
            One category per post. Deleting a category leaves its posts uncategorised.
        </p>
        <a href="{{ route('admin.categories.create') }}"
           class="rounded-lg bg-ink-900 px-4 py-2 text-sm font-medium text-paper-50
                  transition hover:bg-ochre-600 dark:bg-ochre-600 dark:hover:bg-ochre-700">
            New category
        </a>
    </div>

    @if ($categories->isEmpty())
        <div class="mt-6"><x-empty-state title="No categories yet" message="Create one to start organising posts." /></div>
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
                    @foreach ($categories as $category)
                        <tr class="hover:bg-paper-50 dark:hover:bg-ink-800/50">
                            <td class="px-4 py-3">
                                <p class="font-medium">{{ $category->name }}</p>
                                @if ($category->description)
                                    <p class="line-clamp-1 text-xs text-ink-400">{{ $category->description }}</p>
                                @endif
                            </td>
                            <td class="px-4 py-3 font-mono text-xs text-ink-400">{{ $category->slug }}</td>
                            <td class="px-4 py-3">{{ $category->posts_count }}</td>
                            <td class="px-4 py-3">
                                <div class="flex items-center justify-end gap-1">
                                    <a href="{{ route('categories.show', $category) }}" target="_blank" rel="noopener"
                                       class="rounded-lg px-2.5 py-1.5 hover:bg-paper-200 dark:hover:bg-ink-800">View</a>
                                    <a href="{{ route('admin.categories.edit', $category) }}"
                                       class="rounded-lg px-2.5 py-1.5 hover:bg-paper-200 dark:hover:bg-ink-800">Edit</a>
                                    <x-confirm-delete
                                        :action="route('admin.categories.destroy', $category)"
                                        title="Delete this category?"
                                        :message="$category->posts_count > 0
                                            ? $category->posts_count.' post(s) will become uncategorised. The posts themselves are kept.'
                                            : 'This category has no posts.'"
                                    />
                                </div>
                            </td>
                        </tr>
                    @endforeach
                </tbody>
            </table>
        </div>

        <div class="mt-6">{{ $categories->links() }}</div>
    @endif
</x-admin-layout>
