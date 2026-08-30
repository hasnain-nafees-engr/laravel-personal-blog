<?php

use App\Enums\PostStatus;
use App\Events\PostPublished;
use App\Models\Post;
use Illuminate\Console\Scheduling\Schedule;
use Illuminate\Support\Facades\Event;

it('publishes a scheduled post whose time has come', function () {
    $post = Post::factory()->dueForPublishing()->create();

    $this->artisan('posts:publish-scheduled')
        ->expectsOutputToContain('1 post(s) published')
        ->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Published);
});

it('leaves a post scheduled for the future alone', function () {
    $post = Post::factory()->scheduled()->create([
        'published_at' => now()->addWeek(),
    ]);

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);
});

it('reports when nothing is due', function () {
    $this->artisan('posts:publish-scheduled')
        ->expectsOutputToContain('No scheduled posts are due.')
        ->assertSuccessful();
});

it('does not change anything in dry run mode', function () {
    $post = Post::factory()->dueForPublishing()->create();

    $this->artisan('posts:publish-scheduled --dry-run')
        ->expectsOutputToContain('would be published')
        ->assertSuccessful();

    expect($post->fresh()->status)->toBe(PostStatus::Scheduled);
});

it('dispatches PostPublished so caches clear and the action is logged', function () {
    Event::fake([PostPublished::class]);

    Post::factory()->dueForPublishing()->create();

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    Event::assertDispatched(PostPublished::class);
});

it('makes the post publicly visible afterwards', function () {
    $post = Post::factory()->dueForPublishing()->create(['title' => 'Timed release']);

    // Before: not public.
    $this->get(route('posts.show', $post))->assertNotFound();

    $this->artisan('posts:publish-scheduled')->assertSuccessful();

    // After: public.
    $this->get(route('posts.show', $post))->assertOk()->assertSee('Timed release');
});

it('registers the command on the schedule', function () {
    // why: the command working is only half the story - if it is not
    // scheduled, nothing ever runs it in production.
    $events = collect(app(Schedule::class)->events())
        ->map(fn ($event) => $event->command ?? '');

    expect($events->filter(fn (string $c) => str_contains($c, 'posts:publish-scheduled')))
        ->not->toBeEmpty();
});
