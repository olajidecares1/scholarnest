<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $entries = TimetableEntry::where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('student.timetable.index', [
            'school' => $school,
            'student' => $student,
            'entries' => $entries,
        ]);
    }
}
