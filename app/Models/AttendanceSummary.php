<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Support\HasUuidRouteKey;
use Database\Factories\AttendanceSummaryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One student's attendance for one term, entered as totals rather than
 * marked day by day.
 *
 * The optional alternative to the daily register (AttendanceRecord), used
 * only by schools that switch manual attendance on. The two never write to
 * each other.
 */
class AttendanceSummary extends Model
{
    /** @use HasFactory<AttendanceSummaryFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The fields a teacher enters, in the order they are shown.
     *
     * @var list<string>
     */
    public const DAY_FIELDS = ['days_present', 'days_absent', 'days_late', 'days_excused'];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'class_name',
        'session',
        'term',
        'days_present',
        'days_absent',
        'days_late',
        'days_excused',
        'recorded_by',
        'recorded_by_staff_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
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
     * Every day accounted for, whatever its status. Each day carries exactly
     * one status, as in the daily register, so the four never overlap.
     */
    public function totalDays(): int
    {
        return $this->days_present + $this->days_absent + $this->days_late + $this->days_excused;
    }

    /**
     * Days the student was in school. Late counts as attended, matching
     * AttendanceStatus::isPresentForStats().
     */
    public function attendedDays(): int
    {
        return $this->days_present + $this->days_late;
    }

    /**
     * How the entered days compare with the school's configured number of
     * school days for the term: 'over', 'short', or null when they agree or
     * when the school has not configured a figure to compare against.
     */
    public function discrepancyAgainst(?int $schoolDays): ?string
    {
        return self::discrepancyBetween($this->totalDays(), $schoolDays);
    }

    public static function discrepancyBetween(int $totalDays, ?int $schoolDays): ?string
    {
        if ($schoolDays === null) {
            return null;
        }

        return match (true) {
            $totalDays > $schoolDays => 'over',
            $totalDays < $schoolDays => 'short',
            default => null,
        };
    }

    /**
     * The figures as the report card reads them, the same keys the daily
     * register produces, plus the term's configured school days.
     *
     * @return array{present: int, absent: int, late: int, excused: int, total: int, percent: ?int, school_days: ?int, source: string}
     */
    public function toCardSummary(?int $schoolDays): array
    {
        $total = $this->totalDays();

        return [
            'present' => $this->days_present,
            'absent' => $this->days_absent,
            'late' => $this->days_late,
            'excused' => $this->days_excused,
            'total' => $total,
            'percent' => $total === 0 ? null : (int) round(($this->attendedDays() / $total) * 100),
            'school_days' => $schoolDays,
            'source' => 'manual',
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function recordedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'recorded_by');
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function recordedByStaff(): BelongsTo
    {
        return $this->belongsTo(Staff::class, 'recorded_by_staff_id');
    }
}
