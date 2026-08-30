<?php

declare(strict_types=1);

namespace App\Http\Controllers\Admin;

use App\Enums\PostStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\StorePostRequest;
use App\Http\Requests\UpdatePostRequest;
use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;
use App\Services\PostService;
use Illuminate\Contracts\View\View;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;

/**
 * Full CRUD for posts.
 *
 * A RESOURCE CONTROLLER: the seven method names below (index, create, store,
 * show, edit, update, destroy) are Laravel's convention, so a single line -
 * Route::resource('posts', PostController::class) - generates all seven
 * routes with the right HTTP verbs and route names.
 *
 * PostService is type-hinted in the constructor and the service container
 * builds it automatically - that is dependency injection: this class asks for
 * what it needs and never calls `new PostService`.
 */
class PostController extends Controller
{
    public function __construct(private readonly PostService $posts) {}

    /** GET /admin/posts */
    public function index(Request $request): View
    {
        $this->authorize('viewAny', Post::class);

        $posts = Post::query()
            ->with(['category', 'user'])
            ->withCount('comments')
            // Authors see only their own work; admins see everything.
            ->unless($request->user()->isAdmin(),
                fn ($q) => $q->where('user_id', $request->user()->id))
            ->when($request->string('status')->toString(),
                fn ($q, string $status) => $q->where('status', $status))
            ->when($request->string('q')->toString(),
                fn ($q, string $term) => $q->search($term))
            ->latest('updated_at')
            ->paginate((int) config('blog.admin_per_page'))
            ->withQueryString();

        return view('admin.posts.index', [
            'posts' => $posts,
            'statuses' => PostStatus::cases(),
        ]);
    }

    /** GET /admin/posts/create */
    public function create(): View
    {
        $this->authorize('create', Post::class);

        return view('admin.posts.create', [
            'post' => new Post(['status' => PostStatus::Draft]),
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'selectedTags' => [],
        ]);
    }

    /** POST /admin/posts */
    public function store(StorePostRequest $request): RedirectResponse
    {
        // Authorisation already ran inside the Form Request's authorize().
        $post = $this->posts->create(
            $request->postAttributes(),
            $request->user(),
            $request->tagIds(),
            $request->file('cover_image'),
        );

        return redirect()
            ->route('admin.posts.edit', $post)
            ->with('status', __('blog.post_created'));
    }

    /** GET /admin/posts/{post}/edit */
    public function edit(Post $post): View
    {
        $this->authorize('update', $post);

        $post->load('tags');

        return view('admin.posts.edit', [
            'post' => $post,
            'categories' => Category::orderBy('name')->get(),
            'tags' => Tag::orderBy('name')->get(),
            'selectedTags' => $post->tags->pluck('id')->all(),
        ]);
    }

    /** PUT/PATCH /admin/posts/{post} */
    public function update(UpdatePostRequest $request, Post $post): RedirectResponse
    {
        $this->posts->update(
            $post,
            $request->postAttributes(),
            $request->tagIds(),
            $request->file('cover_image'),
        );

        return redirect()
            ->route('admin.posts.edit', $post)
            ->with('status', __('blog.post_updated'));
    }

    /** DELETE /admin/posts/{post} - soft delete, recoverable. */
    public function destroy(Post $post): RedirectResponse
    {
        $this->authorize('delete', $post);

        $this->posts->delete($post);

        return redirect()
            ->route('admin.posts.index')
            ->with('status', __('blog.post_deleted'));
    }

    /** Bring a trashed post back. */
    public function restore(int $id): RedirectResponse
    {
        $post = Post::onlyTrashed()->findOrFail($id);

        $this->authorize('restore', $post);

        $post->restore();

        return redirect()
            ->route('admin.posts.index')
            ->with('status', __('blog.post_restored'));
    }
}
