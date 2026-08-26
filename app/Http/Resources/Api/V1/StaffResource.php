<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Staff;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Staff
 */
class StaffResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'staff_id' => $this->staff_id,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name.' '.$this->last_name),
            'email' => $this->email,
            'role' => $this->role?->value,
            'is_active' => (bool) $this->is_active,
            'school' => new SchoolResource($this->whenLoaded('school')),
        ];
    }
}
