<?php

use App\Models\Category;
use App\Models\Post;
use App\Models\Tag;

/*
| Categories and tags are site-wide structure, so the custom
| EnsureUserIsAdmin middleware guards them. These tests prove it.
*/

it('does not let a guest reach the category admin', function () {
    $this->get(route('admin.categories.index'))->assertRedirect(route('login'));
});

it('does not let an author reach the category admin', function () {
    $this->actingAs(author())
        ->get(route('admin.categories.index'))
        ->assertForbidden();
});

it('does not let an author create a category', function () {
    $this->actingAs(author())
        ->post(route('admin.categories.store'), ['name' => 'Sneaky category'])
        ->assertForbidden();

    expect(Category::count())->toBe(0);
});

it('does not let an author reach the tag admin', function () {
    $this->actingAs(author())
        ->get(route('admin.tags.index'))
        ->assertForbidden();
});

it('lets an admin create a category', function () {
    $this->actingAs(admin())
        ->post(route('admin.categories.store'), [
            'name' => 'Engineering',
            'description' => 'Notes from building things.',
        ])
        ->assertRedirect(route('admin.categories.index'));

    $this->assertDatabaseHas('categories', [
        'name' => 'Engineering',
        'slug' => 'engineering',
    ]);
});

it('lets an admin update a category', function () {
    $category = Category::factory()->create(['name' => 'Old name']);

    $this->actingAs(admin())
        ->put(route('admin.categories.update', $category), ['name' => 'New name'])
        ->assertRedirect();

    expect($category->fresh()->name)->toBe('New name');
});

it('rejects a duplicate category slug', function () {
    Category::factory()->create(['slug' => 'engineering']);

    $this->actingAs(admin())
        ->post(route('admin.categories.store'), ['name' => 'Engineering'])
        ->assertSessionHasErrors('slug');
});

it('keeps posts when their category is deleted', function () {
    $category = Category::factory()->create();
    $post = Post::factory()->published()->for($category)->create();

    $this->actingAs(admin())
        ->delete(route('admin.categories.destroy', $category))
        ->assertRedirect(route('admin.categories.index'));

    // nullOnDelete: the article survives, merely uncategorised.
    expect($post->fresh())->not->toBeNull()
        ->and($post->fresh()->category_id)->toBeNull();
});

it('lets an admin create a tag', function () {
    $this->actingAs(admin())
        ->post(route('admin.tags.store'), ['name' => 'Docker'])
        ->assertRedirect(route('admin.tags.index'));

    $this->assertDatabaseHas('tags', ['name' => 'Docker', 'slug' => 'docker']);
});

it('keeps posts when a tag is deleted', function () {
    $tag = Tag::factory()->create();
    $post = Post::factory()->published()->create();
    $post->tags()->attach($tag);

    $this->actingAs(admin())->delete(route('admin.tags.destroy', $tag));

    // Only the pivot row cascades away.
    expect($post->fresh())->not->toBeNull()
        ->and($post->fresh()->tags)->toHaveCount(0);
    $this->assertDatabaseMissing('post_tag', ['tag_id' => $tag->id]);
});

it('validates the category name', function () {
    $this->actingAs(admin())
        ->post(route('admin.categories.store'), ['name' => ''])
        ->assertSessionHasErrors('name');
});
