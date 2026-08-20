<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        $entries = TimetableEntry::where('staff_id', $staff->id)
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('staff.timetable.index', [
            'school' => $school,
            'staff' => $staff,
            'entries' => $entries,
        ]);
    }
}
