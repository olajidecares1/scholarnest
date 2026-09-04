<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use App\Support\SubmissionTopic;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A report of pupil misconduct outside school, sent in by a member of the
 * public.
 *
 * Belongs to one school and is only ever read by that school's own
 * administrators.
 */
class MisconductReport extends Model
{
    use HasFactory, HasUuidRouteKey;

    public const STATUS_NEW = 'new';

    public const STATUS_REVIEWED = 'reviewed';

    public const STATUS_DISMISSED = 'dismissed';

    protected $fillable = [
        'school_id',
        'reporter_name',
        'subject',
        'description',
        'location',
        'status',
        'reviewed_by',
        'reviewed_at',
        'review_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<MisconductReportAttachment, $this>
     */
    public function attachments(): HasMany
    {
        return $this->hasMany(MisconductReportAttachment::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function reviewer(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    /**
     * What the inbox lists this under. The subject when the reporter gave one,
     * and a summary drawn from the description when they did not - the field is
     * optional on purpose. See [App\Support\SubmissionTopic].
     */
    public function topic(): string
    {
        return SubmissionTopic::from($this->subject, $this->description);
    }

    public function isNew(): bool
    {
        return $this->status === self::STATUS_NEW;
    }

    public function statusLabel(): string
    {
        return match ($this->status) {
            self::STATUS_REVIEWED => 'Reviewed',
            self::STATUS_DISMISSED => 'Dismissed',
            default => 'Awaiting review',
        };
    }
}
