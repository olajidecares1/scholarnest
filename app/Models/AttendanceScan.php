<?php

namespace App\Models;

use App\Enums\CheckInKind;
use App\Enums\CheckInOutcome;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One tap of a phone against the poster, refusals included.
 *
 * This is the log, not the register. The register (AttendanceRecord and
 * StaffAttendanceRecord) says when somebody arrived; this says every time
 * anybody tried, from where, how far away they were, and whether the phone had
 * been carrying the scan around since before it had signal.
 *
 * It is also what makes sending twice safe. `client_uuid` is minted on the
 * device when the scan happens, so a queued scan that is sent, times out, and
 * is sent again lands on the same row both times.
 */
class AttendanceScan extends Model
{
    use HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'staff_id',
        'client_uuid',
        'scanned_at',
        'received_at',
        'kind',
        'outcome',
        'latitude',
        'longitude',
        'accuracy_metres',
        'distance_metres',
        'was_queued',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'scanned_at' => 'datetime',
            'received_at' => 'datetime',
            'kind' => CheckInKind::class,
            'outcome' => CheckInOutcome::class,
            'latitude' => 'float',
            'longitude' => 'float',
            'was_queued' => 'boolean',
        ];
    }

    /**
     * Whoever held the phone, whichever of the two columns holds them.
     */
    public function personName(): string
    {
        $person = $this->student ?? $this->staff;

        return $person ? trim("{$person->first_name} {$person->last_name}") : 'Unknown';
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
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
