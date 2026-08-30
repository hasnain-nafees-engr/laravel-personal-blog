<?php

use App\Http\Controllers\Api\PostController;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Route;

/*
|--------------------------------------------------------------------------
| API routes
|--------------------------------------------------------------------------
| Loaded by bootstrap/app.php with the /api prefix already applied, so the
| routes below live at /api/posts and /api/posts/{slug}.
|
| Read-only by design: this API exists so another site (or a mobile app)
| could list articles. Nothing here changes data, so nothing here needs auth.
*/

Route::middleware('throttle:api')->group(function () {
    Route::get('/posts', [PostController::class, 'index'])->name('api.posts.index');
    Route::get('/posts/{post}', [PostController::class, 'show'])->name('api.posts.show');
});

// Sanctum is installed and ready. The day a write endpoint is needed, it goes
// in a group like this one - the token check is already wired up.
Route::middleware('auth:sanctum')->get('/user', fn (Request $request) => $request->user());
