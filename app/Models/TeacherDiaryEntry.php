<?php

namespace App\Models;

use App\Enums\DiaryEntryStatus;
use App\Enums\ExamTerm;
use App\Support\HasUuidRouteKey;
use Database\Factories\TeacherDiaryEntryFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One week's topic, for one subject, in one class.
 *
 * The diary is a teacher's record of what was actually taught, and the
 * school's way of reading it back. Every entry names its own school, teacher,
 * class, subject, session, term and week, so a query along any of those is a
 * where clause rather than a reconstruction.
 */
class TeacherDiaryEntry extends Model
{
    /** @use HasFactory<TeacherDiaryEntryFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'staff_id',
        'class_name',
        'subject',
        'session',
        'term',
        'week_number',
        'topic',
        'status',
        'seen_by',
        'seen_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => ExamTerm::class,
            'status' => DiaryEntryStatus::class,
            'week_number' => 'integer',
            'seen_at' => 'datetime',
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
     * @return BelongsTo<Staff, $this>
     */
    public function teacher(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'staff_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function seenBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'seen_by');
    }

    public function hasBeenSeen(): bool
    {
        return $this->status === DiaryEntryStatus::Seen;
    }

    /**
     * @param  Builder<TeacherDiaryEntry>  $query
     */
    public function scopeForSchool(Builder $query, School|int $school): void
    {
        $query->where('school_id', $school instanceof School ? $school->id : $school);
    }
}
