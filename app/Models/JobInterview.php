<?php

namespace App\Models;

use App\Enums\InterviewMode;
use App\Support\HasUuidRouteKey;
use Carbon\CarbonInterface;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An interview a school has invited an applicant to.
 */
class JobInterview extends Model
{
    use HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'job_application_id',
        'scheduled_for',
        'mode',
        'location',
        'meeting_link',
        'instructions',
        'message',
        'invited_by',
        'notified_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scheduled_for' => 'datetime',
            'mode' => InterviewMode::class,
            'notified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<JobApplication, $this>
     */
    public function application(): BelongsTo
    {
        return $this->belongsTo(JobApplication::class, 'job_application_id');
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * The date and time as the school means them, in the school's timezone.
     */
    public function localScheduledFor(): CarbonInterface
    {
        return $this->scheduled_for->copy()->setTimezone($this->school?->timezone ?: config('app.timezone'));
    }
}
