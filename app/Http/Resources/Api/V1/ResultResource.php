<?php

namespace App\Http\Resources\Api\V1;

use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * A whole report card.
 *
 * Wraps the array App\Services\ReportCardData produces, which is the same
 * array the School Admin, Staff, Student and Guardian pages all render. That
 * is the point: the API is another reader of the one report card, not a second
 * implementation of it, so a change to how a card is calculated cannot leave
 * the mobile app describing a different result from the printed one.
 *
 * @property-read array<string, mixed> $resource
 */
class ResultResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $data = $this->resource;

        return [
            'examination' => new ExaminationResource($data['examination']),
            'student' => new StudentResource($data['student']),
            'subjects' => SubjectScoreResource::collection($data['subjects']),

            'summary' => [
                'subjects_graded' => $data['summary']['subjectsGraded'],
                'average_score' => $data['summary']['averageScore'],
                'average_percentage' => $data['summary']['average'],
                'position' => $data['summary']['position'],
                'number_in_class' => $data['numberInClass'],
            ],

            'attendance' => $data['attendance'],

            'remarks' => [
                'class_teacher' => $data['report']->teacher_remark,
                'principal' => $data['report']->principal_remark,
            ],

            'class_teacher' => $data['classTeacher']?->staff
                ? new StaffResource($data['classTeacher']->staff)
                : null,

            'next_term_begins' => $data['nextTermBegins']?->toDateString(),
        ];
    }
}
