<?php

namespace App\Http\Resources\Api\V1;

use App\Models\Examination;
use App\Models\Student;
use App\Services\ResultAccessPolicy;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One examination, and, when a student is named, whether they may see it.
 *
 * The access flags are optional rather than always present because the same
 * examination means different things to different readers: a list a school
 * publishes has no reader to be withheld from, and a student's own list has
 * exactly one.
 *
 * Nothing here is a score. A client gets the list, learns which entries it can
 * open, and asks for those one at a time, so a locked result is never
 * serialised in the first place rather than being serialised and then filtered.
 *
 * @mixin Examination
 */
class ExaminationResource extends JsonResource
{
    private ?Student $student = null;

    /**
     * Include this student's access to the examination in the payload.
     */
    public function forStudent(Student $student): self
    {
        $this->student = $student;

        return $this;
    }

    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        return [
            'id' => $this->uuid,
            'name' => $this->name,
            'term' => $this->term?->value,
            'session' => $this->session,
            'class_name' => $this->class_name,
            'exam_date' => $this->exam_date?->toDateString(),

            $this->mergeWhen($this->student !== null, fn () => $this->accessFor($this->student)),
        ];
    }

    /**
     * @return array<string, mixed>
     */
    private function accessFor(Student $student): array
    {
        $policy = app(ResultAccessPolicy::class);
        $withheld = $policy->isLocked($student, $this->resource);

        return [
            // Money. The school is withholding this result over a balance.
            'is_withheld' => $withheld,
            'withheld_reason' => $withheld ? $policy->lockMessage($student, $this->resource) : null,

            // Permission. Even when nothing is withheld, a portal result opens
            // only against its exam token, see the detail endpoint.
            'requires_exam_token' => true,
        ];
    }
}
