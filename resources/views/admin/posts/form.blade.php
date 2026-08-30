{{-- Shared create/edit form.

     why a partial and not a component: it needs six variables from the parent
     ($post, $categories, $tags, $selectedTags, $action, $method) and exists
     only to avoid writing the same 120 lines twice. A component would mean
     declaring six @props for zero reuse elsewhere. --}}
<form method="POST" action="{{ $action }}" enctype="multipart/form-data" class="grid gap-6 lg:grid-cols-3">
    @csrf
    @if ($method !== 'POST')
        {{-- Browsers only send GET and POST, so Laravel reads this hidden
             field to know the request is really a PUT. --}}
        @method($method)
    @endif

    <div class="space-y-5 lg:col-span-2">
        <div>
            <label for="title" class="block text-sm font-medium">Title</label>
            <input type="text" id="title" name="title" value="{{ old('title', $post->title) }}"
                   required maxlength="180" autofocus
                   @error('title') aria-invalid="true" @enderror
                   class="mt-1 w-full rounded-lg border-paper-300 bg-white focus:border-ochre-500
                          focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
            @error('title') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="slug" class="block text-sm font-medium">
                Slug <span class="font-normal text-ink-400">(leave blank to generate from the title)</span>
            </label>
            <input type="text" id="slug" name="slug" value="{{ old('slug', $post->slug) }}" maxlength="200"
                   class="mt-1 w-full rounded-lg border-paper-300 bg-white font-mono text-sm
                          focus:border-ochre-500 focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
            @error('slug') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="excerpt" class="block text-sm font-medium">
                Excerpt <span class="font-normal text-ink-400">(shown on cards and in search results)</span>
            </label>
            <textarea id="excerpt" name="excerpt" rows="2" maxlength="500"
                      class="mt-1 w-full rounded-lg border-paper-300 bg-white focus:border-ochre-500
                             focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">{{ old('excerpt', $post->excerpt) }}</textarea>
            @error('excerpt') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div>
            <label for="body" class="block text-sm font-medium">Body <span class="font-normal text-ink-400">(Markdown)</span></label>
            <textarea id="body" name="body" rows="20" required minlength="20"
                      class="mt-1 w-full rounded-lg border-paper-300 bg-white font-mono text-sm/6
                             focus:border-ochre-500 focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">{{ old('body', $post->body) }}</textarea>
            <p class="mt-1 text-xs text-ink-400">
                Raw HTML is stripped when the article is rendered, so paste from anywhere safely.
            </p>
            @error('body') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>
    </div>

    {{-- Sidebar --}}
    <div class="space-y-5">
        <div class="rounded-2xl border border-paper-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900"
             x-data="{ status: @js(old('status', $post->status?->value ?? 'draft')) }">
            <h2 class="font-medium">Publishing</h2>

            <div class="mt-3">
                <label for="status" class="block text-sm font-medium">Status</label>
                <select id="status" name="status" x-model="status"
                        class="mt-1 w-full rounded-lg border-paper-300 bg-white text-sm
                               focus:border-ochre-500 focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
                    @foreach (\App\Enums\PostStatus::cases() as $status)
                        <option value="{{ $status->value }}"
                                @selected(old('status', $post->status?->value) === $status->value)>
                            {{ $status->label() }}
                        </option>
                    @endforeach
                </select>
                @error('status') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            {{-- Only meaningful for published/scheduled - Alpine hides it for drafts. --}}
            <div class="mt-3" x-show="status !== 'draft'" x-cloak>
                <label for="published_at" class="block text-sm font-medium">Publish date</label>
                <input type="datetime-local" id="published_at" name="published_at"
                       value="{{ old('published_at', $post->published_at?->format('Y-m-d\TH:i')) }}"
                       class="mt-1 w-full rounded-lg border-paper-300 bg-white text-sm
                              focus:border-ochre-500 focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
                <p class="mt-1 text-xs text-ink-400" x-show="status === 'scheduled'" x-cloak>
                    The scheduler publishes it automatically at this time.
                </p>
                @error('published_at') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>
        </div>

        <div class="rounded-2xl border border-paper-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <h2 class="font-medium">Organisation</h2>

            <div class="mt-3">
                <label for="category_id" class="block text-sm font-medium">Category</label>
                <select id="category_id" name="category_id"
                        class="mt-1 w-full rounded-lg border-paper-300 bg-white text-sm
                               focus:border-ochre-500 focus:ring-ochre-500 dark:border-ink-700 dark:bg-ink-900">
                    <option value="">Uncategorised</option>
                    @foreach ($categories as $category)
                        <option value="{{ $category->id }}"
                                @selected((int) old('category_id', $post->category_id) === $category->id)>
                            {{ $category->name }}
                        </option>
                    @endforeach
                </select>
                @error('category_id') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </div>

            <fieldset class="mt-4">
                <legend class="text-sm font-medium">Tags <span class="font-normal text-ink-400">(max 8)</span></legend>
                <div class="mt-2 max-h-56 space-y-1.5 overflow-y-auto pr-1">
                    @foreach ($tags as $tag)
                        <label class="flex items-center gap-2 text-sm">
                            <input type="checkbox" name="tags[]" value="{{ $tag->id }}"
                                   @checked(in_array($tag->id, old('tags', $selectedTags), true))
                                   class="rounded border-paper-300 text-ochre-600 focus:ring-ochre-500
                                          dark:border-ink-700 dark:bg-ink-800">
                            {{ $tag->name }}
                        </label>
                    @endforeach
                </div>
                @error('tags') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
            </fieldset>
        </div>

        <div class="rounded-2xl border border-paper-200 bg-white p-4 dark:border-ink-800 dark:bg-ink-900">
            <h2 class="font-medium">Cover image</h2>

            @if ($post->cover_image)
                <img src="{{ Storage::url($post->cover_image) }}" alt="Current cover"
                     class="mt-3 aspect-[16/9] w-full rounded-lg object-cover">
            @endif

            <input type="file" id="cover_image" name="cover_image" accept="image/jpeg,image/png,image/webp"
                   class="mt-3 w-full text-sm file:mr-3 file:rounded-lg file:border-0 file:bg-paper-200
                          file:px-3 file:py-1.5 file:text-sm file:font-medium dark:file:bg-ink-800">
            <p class="mt-1 text-xs text-ink-400">
                JPG, PNG or WebP, up to {{ round(config('blog.cover_max_kb') / 1024, 1) }} MB.
                Large images are resized in the background.
            </p>
            @error('cover_image') <p class="mt-1 text-sm text-rose-600 dark:text-rose-400">{{ $message }}</p> @enderror
        </div>

        <div class="flex items-center gap-2">
            <button type="submit"
                    class="flex-1 rounded-lg bg-ink-900 px-4 py-2.5 text-sm font-medium text-paper-50
                           transition hover:bg-ochre-600 dark:bg-ochre-600 dark:hover:bg-ochre-700">
                Save post
            </button>
            <a href="{{ route('admin.posts.index') }}"
               class="rounded-lg border border-paper-300 px-4 py-2.5 text-sm font-medium
                      hover:bg-paper-100 dark:border-ink-700 dark:hover:bg-ink-800">
                Cancel
            </a>
        </div>
    </div>
</form>
