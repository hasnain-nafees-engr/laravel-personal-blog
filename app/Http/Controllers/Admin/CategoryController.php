<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Http\Controllers\Controller;
use App\Http\Requests\StoreCategoryRequest;
use App\Models\Category;
use App\Support\CacheKeys;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Cache;

class CategoryController extends Controller
{
    public function index(): View
    {
        $this->authorize('viewAny', Category::class);

        return view('admin.categories.index', [
            'categories' => Category::query()
                ->withCount('posts')
                ->orderBy('name')
                ->paginate((int) config('blog.admin_per_page')),
        ]);
    }

    public function create(): View
    {
        $this->authorize('create', Category::class);

        return view('admin.categories.create', ['category' => new Category]);
    }

    public function store(StoreCategoryRequest $request): RedirectResponse
    {
        Category::create($request->validated());
        Cache::forget(CacheKeys::SIDEBAR_CATEGORIES);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', __('blog.category_created'));
    }

    public function edit(Category $category): View
    {
        $this->authorize('update', $category);

        return view('admin.categories.edit', compact('category'));
    }

    public function update(StoreCategoryRequest $request, Category $category): RedirectResponse
    {
        $this->authorize('update', $category);

        $category->update($request->validated());
        Cache::forget(CacheKeys::SIDEBAR_CATEGORIES);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', __('blog.category_updated'));
    }

    public function destroy(Category $category): RedirectResponse
    {
        $this->authorize('delete', $category);

        // The posts FK is nullOnDelete, so articles survive and simply
        // become uncategorised - see the posts migration.
        $category->delete();
        Cache::forget(CacheKeys::SIDEBAR_CATEGORIES);

        return redirect()
            ->route('admin.categories.index')
            ->with('status', __('blog.category_deleted'));
    }
}
