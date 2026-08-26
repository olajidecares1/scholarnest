<?php

namespace App\Http\Resources\Api\V1;

use App\Models\AttendanceRecord;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin AttendanceRecord
 */
class AttendanceRecordResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'date' => $this->date?->toDateString(),
            'status' => $this->status?->value,
            'class_name' => $this->class_name,

            // Not the note. A teacher's remark on an absence is written for
            // the school, and a parent reading it out of context is how a
            // private observation becomes an argument.
        ];
    }
}
