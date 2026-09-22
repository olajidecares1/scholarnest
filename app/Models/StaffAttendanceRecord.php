<?php

namespace App\Models;

use App\Enums\AttendanceStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\StaffAttendanceRecordFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A member of staff's day: when they arrived, and when they left.
 *
 * Deliberately a separate table from the pupils' register rather than a
 * nullable student_id on it. The two are the same shape and nothing else:
 * every screen, every report, every export and the whole of
 * AuthorizesSchoolOwnership already treat attendance_records as "the pupils",
 * and widening it would have made every one of those queries wrong by default
 * unless it remembered to exclude staff rows.
 */
class StaffAttendanceRecord extends Model
{
    /** @use HasFactory<StaffAttendanceRecordFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'staff_id',
        'date',
        'arrived_at',
        'departed_at',
        'status',
        'source',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'date' => 'date:Y-m-d',
            'arrived_at' => 'datetime',
            'departed_at' => 'datetime',
            'status' => AttendanceStatus::class,
        ];
    }

    /**
     * How long they were on site, or null while they are still here.
     */
    public function minutesOnSite(): ?int
    {
        if (! $this->arrived_at || ! $this->departed_at) {
            return null;
        }

        return (int) $this->arrived_at->diffInMinutes($this->departed_at);
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
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }
}
