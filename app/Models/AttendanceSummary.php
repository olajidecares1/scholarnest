<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One pupil's attendance for one term, entered as day counts by hand.
 *
 * The daily register (AttendanceRecord) is one row per pupil per DAY; this is
 * one row per pupil per TERM, for the school that keeps its register on paper
 * and only needs the totals on the report card. See
 * App\Services\Attendance\TermAttendance for how the two are reconciled.
 */
class AttendanceSummary extends Model
{
    use HasUuidRouteKey;

    /**
     * The four day counts, in the order every screen shows them.
     */
    public const COUNTS = ['days_present', 'days_absent', 'days_late', 'days_excused'];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'session',
        'term',
        'class_name',
        'days_present',
        'days_absent',
        'days_late',
        'days_excused',
        'notes',
        'marked_by',
        'marked_by_staff_id',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => ExamTerm::class,
            'days_present' => 'integer',
            'days_absent' => 'integer',
            'days_late' => 'integer',
            'days_excused' => 'integer',
        ];
    }

    /**
     * Every day accounted for. Each school day carries exactly one status, the
     * same rule as the daily register, so the four add up to the term.
     */
    public function totalDays(): int
    {
        return (int) $this->days_present + (int) $this->days_absent + (int) $this->days_late + (int) $this->days_excused;
    }

    /**
     * Days in school. Late counts as attended, exactly as
     * AttendanceStatus::isPresentForStats() has it for the daily register.
     */
    public function daysAttended(): int
    {
        return (int) $this->days_present + (int) $this->days_late;
    }

    public function percent(): ?int
    {
        $total = $this->totalDays();

        return $total === 0 ? null : (int) round(($this->daysAttended() / $total) * 100);
    }

    /**
     * @return array<string, int>
     */
    public function counts(): array
    {
        return collect(self::COUNTS)->mapWithKeys(fn (string $key) => [$key => (int) $this->{$key}])->all();
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function markedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'marked_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function markedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'marked_by_staff_id');
    }
}
