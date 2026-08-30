<?php

use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Enums\UserRole;

it('exposes a label and badge classes for every post status', function (PostStatus $status) {
    expect($status->label())->toBeString()->not->toBeEmpty()
        ->and($status->badgeClasses())->toBeString()->not->toBeEmpty();
})->with(PostStatus::cases());

it('exposes a label for every comment status', function (CommentStatus $status) {
    expect($status->label())->toBeString()->not->toBeEmpty()
        ->and($status->badgeClasses())->toBeString()->not->toBeEmpty();
})->with(CommentStatus::cases());

it('exposes a label for every user role', function (UserRole $role) {
    expect($role->label())->toBeString()->not->toBeEmpty();
})->with(UserRole::cases());

it('lists post status values for validation rules', function () {
    expect(PostStatus::values())->toBe(['draft', 'scheduled', 'published']);
});

it('rejects a value that is not a real status', function () {
    // why this matters: a backed enum makes an invalid status impossible to
    // construct, which is the whole reason the column is cast to one.
    expect(fn () => PostStatus::from('wizard'))->toThrow(ValueError::class);
});
