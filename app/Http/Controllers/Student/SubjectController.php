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

        $subjectNames = $assignmentCounts->keys()->merge($examSubjectsByName->keys())->unique()->sort()->values();

        $subjects = $subjectNames->map(function (string $name) use ($assignmentCounts, $examSubjectsByName, $student) {
            $examSubjectIds = ($examSubjectsByName->get($name) ?? collect())->pluck('id');
            $scores = ExaminationScore::with('subject')
                ->where('student_id', $student->id)
                ->whereIn('examination_subject_id', $examSubjectIds)
                ->get();

            return [
                'name' => $name,
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
