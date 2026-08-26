<?php

namespace App\Http\Resources\Api\V1;

use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin TimetableEntry
 */
class TimetableEntryResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'day_of_week' => $this->day_of_week,
            'start_time' => $this->start_time,
            'end_time' => $this->end_time,
            'subject' => $this->subject,
            'room' => $this->room,
            'class_name' => $this->class_name,
        ];
    }
}
