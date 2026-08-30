<form method="POST" action="{{ $action }}" class="max-w-xl space-y-5">
    @csrf
    @if ($method !== 'POST')
        @method($method)
    @endif

    <div>
        <label for="name" class="block text-sm font-medium">Name</label>
        <input type="text" id="name" name="name" value="{{ old('name', $category->name) }}"
               required maxlength="80" autofocus
               class="mt-1 w-full rounded-lg border-paper-300 bg-white focus:border-ochre-500
                      focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
        @error('name') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="slug" class="block text-sm font-medium">
            Slug <span class="font-normal text-ink-400">(generated from the name if left blank)</span>
        </label>
        <input type="text" id="slug" name="slug" value="{{ old('slug', $category->slug) }}" maxlength="100"
               class="mt-1 w-full rounded-lg border-paper-300 bg-white font-mono text-sm
                      focus:border-ochre-500 focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
        @error('slug') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div>
        <label for="description" class="block text-sm font-medium">Description</label>
        <textarea id="description" name="description" rows="3" maxlength="500"
                  class="mt-1 w-full rounded-lg border-paper-300 bg-white focus:border-ochre-500
                         focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">{{ old('description', $category->description) }}</textarea>
        @error('description') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
    </div>

    <div class="flex items-center gap-2">
        <button type="submit"
                class="rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-medium text-paper-50
                       transition hover:bg-ochre-600 dark:bg-ochre-600 dark:hover:bg-ochre-700">
            Save category
        </button>
        <a href="{{ route('admin.categories.index') }}"
           class="rounded-lg border border-paper-300 px-4 py-2.5 text-sm font-medium
                  hover:bg-paper-100 dark:border-ink-700 dark:hover:bg-ink-800">Cancel</a>
    </div>
</form>
