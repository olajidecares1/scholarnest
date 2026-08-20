<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $assignments = Assignment::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->with(['submissions' => fn ($q) => $q->where('student_id', $student->id)])
            ->orderByDesc('due_date')
            ->paginate(15);

        return view('guardian.children.assignments', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,
            'assignments' => $assignments,
        ]);
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
    }
}
