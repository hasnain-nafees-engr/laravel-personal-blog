<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Attributes\Fillable;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One line of the admin audit trail: who did what, to which record.
 */
#[Fillable(['user_id', 'action', 'subject_type', 'subject_id'])]
class ActivityLog extends Model
{
    /** Log rows are never edited, so there is no updated_at column. */
    public const UPDATED_AT = null;

    /** @return BelongsTo<User, $this> */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * The record this entry is about - a Post, a Comment, or anything else
     * we start logging later.
     *
     * why: morphTo reads subject_type (the model class name) plus subject_id
     * and gives back the right model, so one table serves every type.
     *
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /** Convenience recorder used by listeners and controllers. */
    public static function record(string $action, Model $subject, ?int $userId = null): self
    {
        return self::create([
            'user_id' => $userId ?? auth()->id(),
            'action' => $action,
            'subject_type' => $subject->getMorphClass(),
            'subject_id' => $subject->getKey(),
        ]);
    }
}
