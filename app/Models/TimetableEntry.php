<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\TimetableEntryFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class TimetableEntry extends Model
{
    /** @use HasFactory<TimetableEntryFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'class_name',
        'day_of_week',
        'start_time',
        'end_time',
        'subject',
        'staff_id',
        'room',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'day_of_week' => 'integer',
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
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    public function dayLabel(): string
    {
        return match ($this->day_of_week) {
            1 => 'Monday',
            2 => 'Tuesday',
            3 => 'Wednesday',
            4 => 'Thursday',
            5 => 'Friday',
            6 => 'Saturday',
            default => 'Sunday',
        };
    }
}
