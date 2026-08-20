<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\TimetableEntry;
use Illuminate\Http\Request;
use Illuminate\View\View;

class DashboardController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        $todaysClasses = TimetableEntry::where('staff_id', $staff->id)
            ->where('day_of_week', now()->dayOfWeekIso)
            ->orderBy('start_time')
            ->get();

        return view('staff.dashboard', [
            'school' => $school,
            'staff' => $staff,
            'todaysClasses' => $todaysClasses,
        ]);
    }
}
