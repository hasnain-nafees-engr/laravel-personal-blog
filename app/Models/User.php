<?php

namespace App\Models;

use App\Enums\UserRole;
use Database\Factories\UserFactory;
use Illuminate\Contracts\Auth\MustVerifyEmail;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Attributes\Hidden;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Carbon;
use Laravel\Sanctum\HasApiTokens;

/**
 * why the #[Fillable] attribute below: Laravel 13 lets you declare
 * mass-assignment rules as attributes. The classic equivalent is
 * `protected $fillable = [...]` inside the class. Either way the point is the
 * same - a request cannot set `role` (or any other column) just by adding it
 * to the form data.
 *
 * @property int $id
 * @property string $name
 * @property string $email
 * @property Carbon|null $email_verified_at
 * @property string $password
 * @property UserRole $role
 * @property-read Collection<int, Post> $posts
 * @property-read Collection<int, Comment> $comments
 * @property-read Collection<int, Comment> $commentsOnPosts
 */
#[Fillable(['name', 'email', 'password'])]
#[Hidden(['password', 'remember_token'])]
// why implement MustVerifyEmail: Breeze ships email-verification routes and
// fires the Verified event, and that event's constructor requires this
// contract. Without it the scaffolding is quietly broken - the routes exist
// but can never work. Implementing it does NOT force anyone to verify; that
// only happens on routes given the 'verified' middleware, and none are.
class User extends Authenticatable implements MustVerifyEmail
{
    /** @use HasFactory<UserFactory> */
    use HasApiTokens, HasFactory, Notifiable;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return HasMany<Post, $this> */
    public function posts(): HasMany
    {
        return $this->hasMany(Post::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Every comment left on any of this user's posts.
     *
     * why: hasManyThrough - we need comments (far model) but there is no
     * user_id on them; the link runs through posts (intermediate model).
     * Without it the admin dashboard would need a join written by hand.
     *
     * @return HasManyThrough<Comment, Post, $this>
     */
    public function commentsOnPosts(): HasManyThrough
    {
        return $this->hasManyThrough(Comment::class, Post::class);
    }

    /** @return HasMany<ActivityLog, $this> */
    public function activityLogs(): HasMany
    {
        return $this->hasMany(ActivityLog::class);
    }

    // -----------------------------------------------------------------
    // Helpers
    // -----------------------------------------------------------------

    public function isAdmin(): bool
    {
        return $this->role === UserRole::Admin;
    }
}
