<?php

use App\Jobs\OptimizeCoverImage;
use App\Models\Post;
use Illuminate\Support\Facades\Storage;

/*
|--------------------------------------------------------------------------
| These tests RUN the job. They do not fake the queue.
|--------------------------------------------------------------------------
|
| why that distinction matters: the admin CRUD test asserts the job is
| *dispatched* using Queue::fake(), which is right for testing the controller
| - but a faked queue never executes handle(), so the job's own body was
| completely untested. It shipped calling Image::read(), which is Intervention
| Image v3's name and does not exist in v4, and only an end-to-end upload
| through the running stack revealed it.
|
| The lesson, and the reason this file exists: "the job was dispatched" and
| "the job works" are two different claims and each needs its own test.
|
*/

/** Write a real JPEG of the given size onto the fake public disk. */
function fakeCover(string $path, int $width, int $height): void
{
    $image = imagecreatetruecolor($width, $height);
    imagefill($image, 0, 0, imagecolorallocate($image, 40, 60, 90));

    ob_start();
    imagejpeg($image, null, 90);
    $binary = (string) ob_get_clean();
    imagedestroy($image);

    Storage::disk('public')->put($path, $binary);
}

/** Read the pixel dimensions of a file on the fake public disk. */
function coverSize(string $path): array
{
    return array_slice((array) getimagesize(Storage::disk('public')->path($path)), 0, 2);
}

beforeEach(function () {
    Storage::fake('public');
});

it('shrinks an oversized cover to the configured width', function () {
    $path = 'covers/oversized.jpg';
    fakeCover($path, 2400, 1400);

    $post = Post::factory()->published()->create(['cover_image' => $path]);

    (new OptimizeCoverImage($post->id, $path))->handle();

    [$width, $height] = coverSize($path);

    expect($width)->toBe(config('blog.cover_max_width'))
        // The aspect ratio must survive: 2400x1400 scaled to 1600 wide.
        ->and($height)->toBe(933);
});

it('leaves a small image alone rather than enlarging it', function () {
    $path = 'covers/small.jpg';
    fakeCover($path, 800, 500);

    $post = Post::factory()->published()->create(['cover_image' => $path]);

    (new OptimizeCoverImage($post->id, $path))->handle();

    expect(coverSize($path))->toBe([800, 500]);
});

it('makes the file smaller on disk', function () {
    $path = 'covers/heavy.jpg';
    fakeCover($path, 3000, 2000);

    $before = Storage::disk('public')->size($path);

    $post = Post::factory()->published()->create(['cover_image' => $path]);
    (new OptimizeCoverImage($post->id, $path))->handle();

    expect(Storage::disk('public')->size($path))->toBeLessThan($before);
});

it('does nothing when the file has already been removed', function () {
    $post = Post::factory()->published()->create(['cover_image' => 'covers/gone.jpg']);

    // Must not throw - the image may have been replaced while the job queued.
    (new OptimizeCoverImage($post->id, 'covers/gone.jpg'))->handle();

    expect(Storage::disk('public')->exists('covers/gone.jpg'))->toBeFalse();
});

it('does nothing when the post has been deleted', function () {
    $path = 'covers/orphan.jpg';
    fakeCover($path, 2400, 1400);

    (new OptimizeCoverImage(999999, $path))->handle();

    // Untouched, because the job could not confirm which post it belongs to.
    expect(coverSize($path))->toBe([2400, 1400]);
});

it('does nothing when the post now has a different cover', function () {
    $path = 'covers/replaced.jpg';
    fakeCover($path, 2400, 1400);

    // The editor uploaded a new cover before this job ran.
    $post = Post::factory()->published()->create(['cover_image' => 'covers/newer.jpg']);

    (new OptimizeCoverImage($post->id, $path))->handle();

    expect(coverSize($path))->toBe([2400, 1400]);
});
