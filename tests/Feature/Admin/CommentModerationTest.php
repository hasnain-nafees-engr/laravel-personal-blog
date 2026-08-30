<?php

use App\Enums\CommentStatus;
use App\Models\Comment;
use App\Models\Post;

it('does not let a guest moderate comments', function () {
    $comment = Comment::factory()->pending()->create();

    $this->get(route('admin.comments.index'))->assertRedirect(route('login'));
    $this->patch(route('admin.comments.approve', $comment))->assertRedirect(route('login'));

    expect($comment->fresh()->status)->toBe(CommentStatus::Pending);
});

it('does not let an author moderate comments on another author s post', function () {
    $comment = Comment::factory()->pending()->create();

    $this->actingAs(author())
        ->patch(route('admin.comments.approve', $comment))
        ->assertForbidden();

    expect($comment->fresh()->status)->toBe(CommentStatus::Pending);
});

it('lets an author moderate comments on their own post', function () {
    $author = author();
    $post = Post::factory()->published()->for($author)->create();
    $comment = Comment::factory()->pending()->for($post)->create();

    $this->actingAs($author)
        ->patch(route('admin.comments.approve', $comment))
        ->assertRedirect();

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);
});

it('lets an admin approve a comment', function () {
    $comment = Comment::factory()->pending()->create();

    $this->actingAs(admin())
        ->patch(route('admin.comments.approve', $comment))
        ->assertRedirect();

    expect($comment->fresh()->status)->toBe(CommentStatus::Approved);

    $this->assertDatabaseHas('activity_logs', [
        'action' => 'comment.approved',
        'subject_type' => Comment::class,
        'subject_id' => $comment->id,
    ]);
});

it('lets an admin reject a comment', function () {
    $comment = Comment::factory()->pending()->create();

    $this->actingAs(admin())
        ->patch(route('admin.comments.reject', $comment))
        ->assertRedirect();

    expect($comment->fresh()->status)->toBe(CommentStatus::Rejected);
});

it('lets an admin delete a comment and its replies', function () {
    $parent = Comment::factory()->approved()->create();
    $reply = Comment::factory()->approved()->replyTo($parent)->create();

    $this->actingAs(admin())
        ->delete(route('admin.comments.destroy', $parent))
        ->assertRedirect();

    // cascadeOnDelete on the self-referencing FK takes the reply with it.
    $this->assertDatabaseMissing('comments', ['id' => $parent->id]);
    $this->assertDatabaseMissing('comments', ['id' => $reply->id]);
});

it('shows pending comments by default in the moderation queue', function () {
    Comment::factory()->pending()->create(['body' => 'Needs a decision']);
    Comment::factory()->approved()->create(['body' => 'Already approved']);

    $this->actingAs(admin())
        ->get(route('admin.comments.index'))
        ->assertOk()
        ->assertSee('Needs a decision')
        ->assertDontSee('Already approved');
});

it('can filter the moderation queue by status', function () {
    Comment::factory()->approved()->create(['body' => 'Already approved']);

    $this->actingAs(admin())
        ->get(route('admin.comments.index', ['status' => CommentStatus::Approved->value]))
        ->assertOk()
        ->assertSee('Already approved');
});

it('shows an author only comments on their own posts', function () {
    $author = author();
    $ownPost = Post::factory()->published()->for($author)->create();
    Comment::factory()->pending()->for($ownPost)->create(['body' => 'On my article']);
    Comment::factory()->pending()->create(['body' => 'On someone else s article']);

    $this->actingAs($author)
        ->get(route('admin.comments.index'))
        ->assertOk()
        ->assertSee('On my article')
        ->assertDontSee('On someone else s article');
});

it('shows an approved comment on the public page afterwards', function () {
    $post = Post::factory()->published()->create();
    $comment = Comment::factory()->pending()->for($post)->create(['body' => 'Please let me through']);

    $this->get(route('posts.show', $post))->assertDontSee('Please let me through');

    $this->actingAs(admin())->patch(route('admin.comments.approve', $comment));

    $this->get(route('posts.show', $post))->assertSee('Please let me through');
});
