<?php

use App\Enums\CommentStatus;
use App\Events\CommentSubmitted;
use App\Mail\NewCommentNotification;
use App\Models\Comment;
use App\Models\Post;
use Illuminate\Support\Facades\Crypt;
use Illuminate\Support\Facades\Event;
use Illuminate\Support\Facades\Mail;

beforeEach(function () {
    $this->post = Post::factory()->published()->create();
});

it('accepts a valid comment and holds it for moderation', function () {
    $response = $this->post(route('comments.store', $this->post), [
        'author_name' => 'Ada Lovelace',
        'author_email' => 'ada@example.com',
        'body' => 'This finally made policies click for me, thank you.',
        ...honeypotFields(),
    ]);

    $response->assertRedirect();
    $response->assertSessionHas('status');

    $this->assertDatabaseHas('comments', [
        'post_id' => $this->post->id,
        'author_name' => 'Ada Lovelace',
        // The important assertion: nothing is public until a human approves it.
        'status' => CommentStatus::Pending->value,
    ]);
});

it('does not show a pending comment on the post page', function () {
    Comment::factory()->pending()->for($this->post)->create(['body' => 'Waiting for review']);

    $this->get(route('posts.show', $this->post))
        ->assertOk()
        ->assertDontSee('Waiting for review');
});

it('shows an approved comment on the post page', function () {
    Comment::factory()->approved()->for($this->post)->create(['body' => 'Visible to everyone']);

    $this->get(route('posts.show', $this->post))
        ->assertOk()
        ->assertSee('Visible to everyone');
});

it('does not show a rejected comment', function () {
    Comment::factory()->rejected()->for($this->post)->create(['body' => 'Spam content']);

    $this->get(route('posts.show', $this->post))
        ->assertOk()
        ->assertDontSee('Spam content');
});

it('validates the comment fields', function () {
    $this->post(route('comments.store', $this->post), honeypotFields())
        ->assertSessionHasErrors(['author_name', 'author_email', 'body']);

    expect(Comment::count())->toBe(0);
});

it('rejects an invalid email address', function () {
    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Bot',
        'author_email' => 'not-an-email',
        'body' => 'Some text that is long enough.',
        ...honeypotFields(),
    ])->assertSessionHasErrors('author_email');
});

it('rejects a comment when the honeypot field is filled', function () {
    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Spam Bot',
        'author_email' => 'bot@example.com',
        'body' => 'Buy cheap watches at example.com',
        'website' => 'http://spam.example.com',   // a human never sees this field
        'started_at' => Crypt::encryptString((string) now()->subMinute()->timestamp),
    ])->assertSessionHasErrors('website');

    expect(Comment::count())->toBe(0);
});

it('rejects a comment submitted faster than a human could type', function () {
    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Speedy Bot',
        'author_email' => 'bot@example.com',
        'body' => 'Instant comment from a script.',
        'website' => '',
        'started_at' => Crypt::encryptString((string) now()->timestamp), // 0 seconds ago
    ])->assertSessionHasErrors('website');

    expect(Comment::count())->toBe(0);
});

it('rejects a tampered timestamp', function () {
    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Clever Bot',
        'author_email' => 'bot@example.com',
        'body' => 'Trying to fake the timestamp.',
        'website' => '',
        'started_at' => 'not-encrypted-at-all',
    ])->assertSessionHasErrors('website');
});

it('refuses a reply that belongs to a different post', function () {
    $otherPost = Post::factory()->published()->create();
    $foreignComment = Comment::factory()->approved()->for($otherPost)->create();

    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Ada',
        'author_email' => 'ada@example.com',
        'body' => 'Grafting a reply onto the wrong thread.',
        'parent_id' => $foreignComment->id,
        ...honeypotFields(),
    ])->assertSessionHasErrors('parent_id');
});

it('rate limits comments to three per minute', function () {
    // The fourth attempt must be blocked by throttle:comments.
    foreach (range(1, 3) as $i) {
        $this->post(route('comments.store', $this->post), [
            'author_name' => "Commenter {$i}",
            'author_email' => "person{$i}@example.com",
            'body' => "A perfectly reasonable comment number {$i}.",
            ...honeypotFields(),
        ])->assertRedirect();
    }

    // The limiter is configured with a ->response() that redirects back with
    // a readable message rather than showing a bare 429 page - a form is a
    // much better place to explain the limit than an error screen.
    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Commenter 4',
        'author_email' => 'person4@example.com',
        'body' => 'One comment too many for this minute.',
        ...honeypotFields(),
    ])
        ->assertRedirect()
        ->assertSessionHasErrors('body');

    expect(Comment::count())->toBe(3);
});

it('cannot comment on an unpublished post', function () {
    $draft = Post::factory()->draft()->create();

    $this->post(route('comments.store', $draft), [
        'author_name' => 'Ada',
        'author_email' => 'ada@example.com',
        'body' => 'Commenting on something not public.',
        ...honeypotFields(),
    ])->assertNotFound();
});

it('dispatches the CommentSubmitted event', function () {
    Event::fake([CommentSubmitted::class]);

    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Ada',
        'author_email' => 'ada@example.com',
        'body' => 'An event should be raised for this comment.',
        ...honeypotFields(),
    ]);

    Event::assertDispatched(CommentSubmitted::class);
});

it('emails the post author about a new comment', function () {
    Mail::fake();

    $this->post(route('comments.store', $this->post), [
        'author_name' => 'Ada',
        'author_email' => 'ada@example.com',
        'body' => 'The author should hear about this one.',
        ...honeypotFields(),
    ]);

    Mail::assertSent(NewCommentNotification::class, function (NewCommentNotification $mail) {
        return $mail->hasTo($this->post->user->email);
    });
});

it('records a logged in user against their comment', function () {
    $user = author();

    $this->actingAs($user)->post(route('comments.store', $this->post), [
        'body' => 'Commenting while signed in.',
        ...honeypotFields(),
    ])->assertRedirect();

    // Name and email are taken from the account, not from the form.
    $this->assertDatabaseHas('comments', [
        'user_id' => $user->id,
        'author_name' => $user->name,
        'author_email' => $user->email,
    ]);
});

it('escapes html in a comment author name', function () {
    Comment::factory()->approved()->for($this->post)->create([
        'author_name' => '<script>alert(1)</script>',
        'body' => 'A normal looking comment.',
    ]);

    $this->get(route('posts.show', $this->post))
        ->assertOk()
        ->assertDontSee('<script>alert(1)</script>', escape: false);
});
