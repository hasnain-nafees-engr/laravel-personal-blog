<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreTagRequest;
use App\Models\Tag;
use App\Support\CacheKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class TagController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Tag::class);

        return view('admin.tags.index', [
            'tags' => Tag::query()
                ->withCount('posts')
                ->orderBy('name')
                ->paginate((int) config('blog.admin_per_page')),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Tag::class);

        return view('admin.tags.create', ['tag' => new Tag]);
    }

    public function store(StoreTagRequest $request): RedirectResponse
    {
        Tag::create($request->validated());
        Cache::forget(CacheKeys::SIDEBAR_TAGS);

        return redirect()->route('admin.tags.index')->with('status', __('blog.tag_created'));
    }

    public function edit(Tag $tag): View
    {
        $this->authorize('update', $tag);

        return view('admin.tags.edit', compact('tag'));
    }

    public function update(StoreTagRequest $request, Tag $tag): RedirectResponse
    {
        $this->authorize('update', $tag);

        $tag->update($request->validated());
        Cache::forget(CacheKeys::SIDEBAR_TAGS);

        return redirect()->route('admin.tags.index')->with('status', __('blog.tag_updated'));
    }

    public function destroy(Tag $tag): RedirectResponse
    {
        $this->authorize('delete', $tag);

        // Pivot rows cascade away with the tag; posts are untouched.
        $tag->delete();
        Cache::forget(CacheKeys::SIDEBAR_TAGS);

        return redirect()->route('admin.tags.index')->with('status', __('blog.tag_deleted'));
    }
}
