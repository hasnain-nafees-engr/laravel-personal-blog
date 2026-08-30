<?php

use App\Http\Controllers\Admin;
use App\Http\Controllers\CategoryController;
use App\Http\Controllers\CommentController;
use App\Http\Controllers\FeedController;
use App\Http\Controllers\HomeController;
use App\Http\Controllers\PostController;
use App\Http\Controllers\ProfileController;
use App\Http\Controllers\SitemapController;
use App\Http\Controllers\TagController;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| Public routes
|--------------------------------------------------------------------------
| Every route is NAMED. Views then link with route('posts.show', $post)
| instead of hard-coding "/posts/{$post->slug}" - change the URL here and
| every link in the application follows.
*/

Route::get('/', HomeController::class)->name('home');
Route::get('/posts', [PostController::class, 'index'])->name('posts.index');

// {post} becomes a Post via route model binding, matched on `slug` because
// the model defines getRouteKeyName(). An unknown slug 404s automatically.
Route::get('/posts/{post}', [PostController::class, 'show'])->name('posts.show');

Route::get('/categories/{category}', [CategoryController::class, 'show'])->name('categories.show');
Route::get('/tags/{tag}', [TagController::class, 'show'])->name('tags.show');

// throttle:comments = the named rate limiter defined in AppServiceProvider
// (3 per minute, per IP).
Route::post('/posts/{post}/comments', [CommentController::class, 'store'])
    ->middleware('throttle:comments')
    ->name('comments.store');

// SEO endpoints.
Route::get('/sitemap.xml', SitemapController::class)->name('sitemap');
Route::get('/feed.xml', FeedController::class)->name('feed');

/*
|--------------------------------------------------------------------------
| Authenticated user profile (Breeze)
|--------------------------------------------------------------------------
*/

Route::middleware('auth')->group(function () {
    Route::get('/profile', [ProfileController::class, 'edit'])->name('profile.edit');
    Route::patch('/profile', [ProfileController::class, 'update'])->name('profile.update');
    Route::delete('/profile', [ProfileController::class, 'destroy'])->name('profile.destroy');
});

// why this exists: Breeze redirects to route('dashboard') after login,
// registration, email verification and password confirmation. Our dashboard
// lives at /admin, so rather than editing six Breeze controllers (and having
// to redo it on every Breeze update) we keep the name pointing at the real
// page. One redirect beats six edits.
Route::redirect('/dashboard', '/admin')->name('dashboard');

/*
|--------------------------------------------------------------------------
| Admin panel
|--------------------------------------------------------------------------
| A ROUTE GROUP applies the same prefix, name prefix and middleware stack to
| everything inside, so no individual route can accidentally be left public.
|
|   prefix('admin')  -> URLs start /admin/...
|   name('admin.')   -> route names start admin....
|   middleware('auth') -> must be signed in
|
| TWO LAYERS OF AUTHORISATION, and they answer different questions:
|
|   middleware  "may this user be in this AREA at all?"  (coarse, runs first)
|   policy      "may this user touch THIS RECORD?"       (fine, per model)
|
| So authors reach /admin/posts and see their own drafts (PostPolicy decides
| what they may edit), while the taxonomy that shapes the whole site is
| wrapped in our custom 'admin' middleware below.
*/

Route::middleware('auth')
    ->prefix('admin')
    ->name('admin.')
    ->group(function () {
        Route::get('/', Admin\DashboardController::class)->name('dashboard');

        // Restore must be declared BEFORE the resource route, otherwise
        // /admin/posts/{post} would try to match "restore" as a slug.
        Route::patch('/posts/{id}/restore', [Admin\PostController::class, 'restore'])
            ->name('posts.restore');

        Route::get('/posts/{post}/preview', Admin\PostPreviewController::class)
            ->name('posts.preview');

        // RESOURCE CONTROLLERS: one line each, seven RESTful routes.
        // `show` is excluded - the admin list links straight to edit, and the
        // public page is where you look at a post.
        Route::resource('posts', Admin\PostController::class)->except(['show']);

        // Comments are moderated, not created or edited, so only these.
        // CommentPolicy lets an author moderate their own posts' threads.
        Route::get('/comments', [Admin\CommentController::class, 'index'])->name('comments.index');
        Route::patch('/comments/{comment}/approve', [Admin\CommentController::class, 'approve'])->name('comments.approve');
        Route::patch('/comments/{comment}/reject', [Admin\CommentController::class, 'reject'])->name('comments.reject');
        Route::delete('/comments/{comment}', [Admin\CommentController::class, 'destroy'])->name('comments.destroy');

        // Site-wide taxonomy: administrators only. This is where the custom
        // EnsureUserIsAdmin middleware earns its place - an author has no
        // business renaming the categories every other author writes under.
        Route::middleware('admin')->group(function () {
            Route::resource('categories', Admin\CategoryController::class)->except(['show']);
            Route::resource('tags', Admin\TagController::class)->except(['show']);
        });
    });

require __DIR__.'/auth.php';
