<?php

namespace App\Http\Resources\Api\V1;

use App\Models\ExaminationSubject;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\JsonResource;

/**
 * One subject on a report card, with this student's marks in it.
 *
 * Percentage and grade are read from the score itself rather than recomputed
 * here. They come from the school's own grade bands, and a resource that did
 * its own arithmetic would be a second opinion on a number the report card has
 * already settled.
 *
 * @mixin ExaminationSubject
 */
class SubjectScoreResource extends JsonResource
{
    /**
     * @return array<string, mixed>
     */
    public function toArray(Request $request): array
    {
        $score = $this->scores->first();

        return [
            'subject' => $this->name,
            'max_score' => $this->max_score,
            'test_max_score' => $this->testMaxScore(),
            'exam_max_score' => $this->examMaxScore(),

            // Cast, because the columns are decimals and Eloquent hands those
            // back as strings, "72.00" in JSON is a mark a client has to
            // parse before it can compare it to anything.
            'test_score' => $score?->test_score === null ? null : (float) $score->test_score,
            'exam_score' => $score?->exam_score === null ? null : (float) $score->exam_score,
            'total' => $score?->score === null ? null : (float) $score->score,
            'percentage' => $score ? round($score->percentage(), 1) : null,
            'grade' => $score?->grade(),
            'remark' => $score?->remark,

            // A subject the teacher has not marked yet is present in the list
            // with nulls rather than absent from it, so a client can show the
            // whole card and say which rows are still to come.
            'is_graded' => $score !== null,
        ];
    }
}
