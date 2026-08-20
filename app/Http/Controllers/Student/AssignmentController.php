<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $assignments = Assignment::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderByDesc('due_date')
            ->paginate(15);

        return view('student.assignments.index', [
            'school' => $school,
            'student' => $student,
            'assignments' => $assignments,
        ]);
    }
}
