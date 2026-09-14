<?php

namespace App\Models;

use App\Enums\ResultTokenAccessOutcome;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One attempt to redeem a result token.
 *
 * Written for every attempt, including the ones that identified nothing. Rows
 * are only ever created, nothing in the application updates or deletes one,
 * because a log that can be rewritten after the fact is not evidence.
 *
 * The token itself is never stored here, only its hash, which is enough to see
 * the same wrong value being tried over and over without the log becoming a
 * list of live credentials.
 */
class ResultTokenAccessLog extends Model
{
    use HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'result_checking_pin_id',
        'student_id',
        'examination_id',
        'outcome',
        'token_hash_attempted',
        'ip_address',
        'user_agent',
        'occurred_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'outcome' => ResultTokenAccessOutcome::class,
            'occurred_at' => 'datetime',
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
     * @return BelongsTo<ResultCheckingPin, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(ResultCheckingPin::class, 'result_checking_pin_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * Keeps one school's access log away from another's.
     *
     * @param  Builder<ResultTokenAccessLog>  $query
     */
    public function scopeForSchool(Builder $query, School|int $school): void
    {
        $query->where('school_id', $school instanceof School ? $school->id : $school);
    }

    /**
     * @param  Builder<ResultTokenAccessLog>  $query
     */
    public function scopeFailed(Builder $query): void
    {
        $query->where('outcome', '!=', ResultTokenAccessOutcome::Succeeded->value);
    }

    /**
     * The attempts worth looking at: guessing, and rate-limited bursts.
     *
     * @param  Builder<ResultTokenAccessLog>  $query
     */
    public function scopeSuspicious(Builder $query): void
    {
        $query->whereIn('outcome', [
            ResultTokenAccessOutcome::NotFound->value,
            ResultTokenAccessOutcome::RateLimited->value,
            ResultTokenAccessOutcome::Revoked->value,
        ]);
    }

    /**
     * @param  Builder<ResultTokenAccessLog>  $query
     */
    public function scopeSince(Builder $query, int $minutes): void
    {
        $query->where('occurred_at', '>=', now()->subMinutes($minutes));
    }
}
