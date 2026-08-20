<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $records = $student->attendanceRecords()
            ->orderByDesc('date')
            ->paginate(30);

        $monthRecords = $student->attendanceRecords()
            ->whereBetween('date', [now()->startOfMonth(), now()->endOfMonth()])
            ->get();

        $presentCount = $monthRecords->filter(fn ($record) => $record->status->isPresentForStats())->count();
        $percent = $monthRecords->isEmpty() ? 0 : (int) round(($presentCount / $monthRecords->count()) * 100);

        return view('guardian.children.attendance', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,
            'records' => $records,
            'monthPercent' => $percent,
            'monthTotal' => $monthRecords->count(),
            'monthPresent' => $presentCount,
        ]);
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
    }
}
