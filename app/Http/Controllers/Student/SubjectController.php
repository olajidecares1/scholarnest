<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SubjectController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $assignmentCounts = Assignment::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->select('subject')
            ->selectRaw('count(*) as assignment_count')
            ->groupBy('subject')
            ->pluck('assignment_count', 'subject');

        $examSubjectsByName = ExaminationSubject::whereHas('examination', fn ($q) => $q->where('school_id', $student->school_id)
            ->where('class_name', $student->class_name))
            ->get()
            ->groupBy('name');

        // The class's own subjects first, in result-sheet order (a KG, JSS or SS class
        // always has its default subjects), then anything else the student
        // has assignments or results in, A to Z.
        // The student's own school, whatever school the URL names.
        $classSubjects = $student->school->classSubjectsFor($student->class_name);
        $offered = $classSubjects->pluck('name');
        $defaults = $classSubjects->where('is_default', true)->pluck('name');

        $others = $assignmentCounts->keys()->merge($examSubjectsByName->keys())
            ->reject(fn ($name) => $offered->contains(fn ($offeredName) => mb_strtolower(trim($offeredName)) === mb_strtolower(trim((string) $name))))
            ->unique()
            ->sort()
            ->values();

        $subjectNames = $offered->merge($others)->values();

        $subjects = $subjectNames->map(function (string $name) use ($assignmentCounts, $examSubjectsByName, $student, $defaults) {
            $examSubjectIds = ($examSubjectsByName->get($name) ?? collect())->pluck('id');
            $scores = ExaminationScore::with('subject')
                ->where('student_id', $student->id)
                ->whereIn('examination_subject_id', $examSubjectIds)
                ->get();

            return [
                'name' => $name,
                'is_default' => $defaults->contains($name),
                'assignment_count' => (int) $assignmentCounts->get($name, 0),
                'exam_count' => $scores->count(),
                'average_percentage' => $scores->isNotEmpty() ? round($scores->avg(fn (ExaminationScore $score) => $score->percentage()), 1) : null,
            ];
        });

        return view('student.subjects.index', [
            'school' => $school,
            'student' => $student,
            'subjects' => $subjects,
        ]);
    }
}
