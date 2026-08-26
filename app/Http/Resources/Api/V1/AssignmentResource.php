<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Assignment;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Assignment
 */
class AssignmentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'title' => $this->title,
            'subject' => $this->subject,
            'description' => $this->description,
            'class_name' => $this->class_name,
            'due_date' => $this->due_date?->toDateString(),
            'max_score' => $this->max_score,
            'is_overdue' => $this->due_date?->isPast() ?? false,
        ];
    }
}
