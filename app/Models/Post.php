<?php

namespace App\Models;

use App\Contracts\MarkdownRenderer;
use App\Enums\CommentStatus;
use App\Enums\PostStatus;
use App\Support\ReadingTime;
use Database\Factories\PostFactory;
use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Casts\Attribute;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\MorphMany;
use Illuminate\Database\Eloquent\SoftDeletes;
use Illuminate\Support\Carbon;
use Illuminate\Support\Str;

#[Fillable([
    'category_id', 'title', 'slug', 'excerpt', 'body',
    'cover_image', 'status', 'published_at',
])]
class Post extends Model
{
    /** @use HasFactory<PostFactory> */
    use HasFactory, SoftDeletes;

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => PostStatus::class,
            'published_at' => 'datetime',
            'view_count' => 'integer',
        ];
    }

    /**
     * Bind {post} in routes by slug instead of id, so URLs read
     * /posts/why-laravel-policies-matter rather than /posts/17.
     *
     * The alternative is to spell it out per route: {post:slug}. Doing it
     * here keeps every route consistent and impossible to forget.
     */
    public function getRouteKeyName(): string
    {
        return 'slug';
    }

    // -----------------------------------------------------------------
    // Relationships
    // -----------------------------------------------------------------

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /** @return BelongsTo<Category, $this> */
    public function category(): BelongsTo
    {
        return $this->belongsTo(Category::class);
    }

    /** @return BelongsToMany<Tag, $this> */
    public function tags(): BelongsToMany
    {
        return $this->belongsToMany(Tag::class);
    }

    /** @return HasMany<Comment, $this> */
    public function comments(): HasMany
    {
        return $this->hasMany(Comment::class);
    }

    /**
     * Only the comments a moderator let through, newest last, top level only.
     *
     * @return HasMany<Comment, $this>
     */
    public function approvedComments(): HasMany
    {
        return $this->comments()
            ->whereNull('parent_id')
            ->where('status', CommentStatus::Approved)
            ->oldest();
    }

    /** @return MorphMany<ActivityLog, $this> */
    public function activityLogs(): MorphMany
    {
        return $this->morphMany(ActivityLog::class, 'subject');
    }

    // -----------------------------------------------------------------
    // Query scopes (local)
    // -----------------------------------------------------------------

    /**
     * The single definition of "the public may see this".
     *
     * why: written once as a scope so no controller can forget half of the
     * rule and leak a scheduled post. Uses the (status, published_at) index.
     *
     * @param  Builder<Post>  $query
     */
    public function scopePublished(Builder $query): void
    {
        $query->where('status', PostStatus::Published)
            ->where('published_at', '<=', now());
    }

    /** @param  Builder<Post>  $query */
    public function scopeDraft(Builder $query): void
    {
        $query->where('status', PostStatus::Draft);
    }

    /** @param  Builder<Post>  $query */
    public function scopeScheduled(Builder $query): void
    {
        $query->where('status', PostStatus::Scheduled);
    }

    /**
     * Case-insensitive search over title and excerpt.
     *
     * why: ILIKE is PostgreSQL's case-insensitive LIKE. Full-text search
     * (tsvector) would be faster on a large corpus but needs an extra index
     * and stemming decisions - overkill for a personal blog, and this stays
     * readable in an interview.
     *
     * @param  Builder<Post>  $query
     */
    public function scopeSearch(Builder $query, string $term): void
    {
        $term = trim($term);

        if ($term === '') {
            return;
        }

        $like = '%'.str_replace(['%', '_'], ['\%', '\_'], $term).'%';

        $query->where(function (Builder $query) use ($like): void {
            $query->where('title', 'ILIKE', $like)
                ->orWhere('excerpt', 'ILIKE', $like);
        });
    }

    // -----------------------------------------------------------------
    // Accessors
    // -----------------------------------------------------------------

    /**
     * Markdown body rendered to safe HTML.
     *
     * why: the renderer strips raw HTML, so `{!! $post->body_html !!}` in a
     * Blade view cannot inject a <script> tag even if an author pastes one.
     * That is the only reason unescaped output is acceptable here.
     */
    protected function bodyHtml(): Attribute
    {
        return Attribute::make(
            get: fn (): string => app(MarkdownRenderer::class)->render($this->body ?? ''),
        )->shouldCache();
    }

    /** Estimated minutes to read, from the Markdown source. */
    protected function readingTime(): Attribute
    {
        return Attribute::make(
            get: fn (): int => ReadingTime::forText($this->body ?? ''),
        )->shouldCache();
    }

    /** Author-written excerpt, or the opening of the body as a fallback. */
    protected function summary(): Attribute
    {
        return Attribute::make(
            get: fn (): string => filled($this->excerpt)
                ? $this->excerpt
                : Str::limit(strip_tags(app(MarkdownRenderer::class)->render($this->body ?? '')), 160),
        );
    }

    // -----------------------------------------------------------------
    // Behaviour
    // -----------------------------------------------------------------

    public function isPublished(): bool
    {
        return $this->status === PostStatus::Published
            && $this->published_at !== null
            && $this->published_at->lessThanOrEqualTo(Carbon::now());
    }

    /**
     * Count one view without touching updated_at.
     *
     * why: `increment()` issues `SET view_count = view_count + 1` in the
     * database, so two simultaneous readers cannot overwrite each other the
     * way `$post->view_count + 1` in PHP would. `timestamps = false` keeps a
     * view from looking like an edit.
     */
    public function recordView(): void
    {
        $this->timestamps = false;
        $this->increment('view_count');
        $this->timestamps = true;
    }
}
