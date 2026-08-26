<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * @mixin Student
 */
class StudentResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            // The uuid, never the primary key. The whole application addresses
            // records this way so an id cannot be counted up from 1, and an
            // API that leaked the integer would undo that everywhere at once.
            'id' => $this->uuid,
            'admission_number' => $this->admission_number,
            'first_name' => $this->first_name,
            'last_name' => $this->last_name,
            'full_name' => trim($this->first_name.' '.$this->last_name),
            'class_name' => $this->class_name,
            'gender' => $this->gender?->value,
            'date_of_birth' => $this->date_of_birth?->toDateString(),
            'photo_url' => $this->photoUrl(),
            'is_active' => (bool) $this->is_active,

            // Contact details belong to the student and to the school that
            // holds them. A guardian reading their child's record gets the
            // record, not the child's phone number and address.
            $this->mergeWhen($request->user() instanceof Student, fn () => [
                'email' => $this->email,
                'phone' => $this->phone,
                'address' => $this->address,
                'blood_group' => $this->blood_group,
                'house' => $this->house,
            ]),

            'school' => new SchoolResource($this->whenLoaded('school')),
        ];
    }
}
