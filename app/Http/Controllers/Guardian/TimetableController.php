<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function index(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $entries = TimetableEntry::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('guardian.children.timetable', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,
            'entries' => $entries,
        ]);
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
    }
}
