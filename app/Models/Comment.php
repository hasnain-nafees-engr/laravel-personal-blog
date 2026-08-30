<?php

namespace App\Models;

use App\Enums\CommentStatus;
use Database\Factories\CommentFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Collection;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

/**
 * @property int $id
 * @property int $post_id
 * @property int|null $parent_id
 * @property int|null $user_id
 * @property string $author_name
 * @property string $author_email
 * @property string $body
 * @property CommentStatus $status
 * @property Carbon $created_at
 * @property Carbon $updated_at
 * @property-read string $initials
 * @property-read string $avatar_hash
 * @property-read Post|null $post
 * @property-read Comment|null $parent
 * @property-read User|null $user
 * @property-read Collection<int, Comment> $replies
 * @property-read Collection<int, Comment> $approvedReplies
 */
#[Fillable(['post_id', 'parent_id', 'user_id', 'author_name', 'author_email', 'body', 'status'])]
class Comment extends Model
{
    /** @use HasFactory<CommentFactory> */
    use HasFactory;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CommentStatus::class,
        ];
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return BelongsTo<Post, $this> */
    public function post(): BelongsTo
    {
        return $this->belongsTo(Post::class);
    }

    /**
     * The comment this one answers (null for a top-level comment).
     *
     * @return BelongsTo<Comment, $this>
     */
    public function parent(): BelongsTo
    {
        return $this->belongsTo(Comment::class, 'parent_id');
    }

    /**
     * Replies to this comment - the other half of the self-reference.
     *
     * @return HasMany<Comment, $this>
     */
    public function replies(): HasMany
    {
        return $this->hasMany(Comment::class, 'parent_id')->oldest();
    }

    /** @return HasMany<Comment, $this> */
    public function approvedReplies(): HasMany
    {
        return $this->replies()->where('status', CommentStatus::Approved);
    }

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return MorphMany<ActivityLog, $this> */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    // -----------------------------------------------------------------
    // Scopes
    // -----------------------------------------------------------------

    /** @param  Builder<Comment>  $query */
    public function scopeApproved(Builder $query): void
    {
        $query->where('status', CommentStatus::Approved);
    }

    /** @param  Builder<Comment>  $query */
    public function scopePending(Builder $query): void
    {
        $query->where('status', CommentStatus::Pending);
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    /** Initials for the avatar bubble, e.g. "Ada Lovelace" -> "AL". */
    protected function initials(): Attribute
    {
        return Attribute::make(
            get: function (): string {
                $parts = preg_split('/\s+/', trim($this->author_name)) ?: [];
                $letters = array_map(fn (string $p): string => Str::upper(Str::substr($p, 0, 1)), $parts);

                return Str::substr(implode('', $letters), 0, 2) ?: '?';
            },
        );
    }

    /**
     * Gravatar-style hash of the email.
     *
     * why: an accessor, so the raw email never has to reach a Blade view -
     * it stays out of the HTML source entirely.
     */
    protected function avatarHash(): Attribute
    {
        return Attribute::make(
            get: fn (): string => md5(Str::lower(trim($this->author_email))),
        );
    }

    public function isApproved(): bool
    {
        return $this->status === CommentStatus::Approved;
    }
}
