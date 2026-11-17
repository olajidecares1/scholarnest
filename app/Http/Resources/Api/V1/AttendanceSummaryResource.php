<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceSummary;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One term's attendance totals, entered by hand by the school.
 *
 * @mixin AttendanceSummary
 */
class AttendanceSummaryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'session' => $this->session,
            'term' => $this->term?->value,
            'term_label' => $this->term?->label(),
            'class_name' => $this->class_name,
            // The pupil's own school days when the school typed them, else
            // the term's; null when neither is known.
            'school_days' => $this->expectedSchoolDays(),
            'days_present' => (int) $this->days_present,
            'days_absent' => (int) $this->days_absent,
            'days_late' => (int) $this->days_late,
            'days_excused' => (int) $this->days_excused,
            'total_days' => $this->totalDays(),
            'days_attended' => $this->daysAttended(),
            'percent' => $this->percent(),
            'updated_at' => $this->updated_at?->toIso8601String(),

            // Not the note, for the same reason AttendanceRecordResource
            // leaves it out: it is written for the school.
        ];
    }
}
