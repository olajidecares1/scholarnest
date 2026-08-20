<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $records = $student->attendanceRecords()
            ->orderByDesc('date')
            ->paginate(30);

        $monthRecords = $student->attendanceRecords()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();

        $presentCount = $monthRecords->filter(fn ($record) => $record->status->isPresentForStats())->count();
        $percent = $monthRecords->isEmpty() ? 0 : (int) round(($presentCount / $monthRecords->count()) * 100);

        return view('student.attendance.index', [
            'school' => $school,
            'student' => $student,
            'records' => $records,
            'monthPercent' => $percent,
            'monthTotal' => $monthRecords->count(),
            'monthPresent' => $presentCount,
        ]);
    }
}
