<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\AttendanceStatus;
use App\Http\Controllers\Controller;
use App\Models\AttendanceRecord;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Carbon;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AttendanceController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;
        $date = $this->resolveDate($request->input('date'));
        $className = $request->string('class')->toString();

        $students = $school->students()
            ->where('is_active', true)
            ->when($className, fn ($query) => $query->where('class_name', $className))
            ->orderBy('last_name')
            ->get();

        $existing = AttendanceRecord::where('school_id', $school->id)
            ->where('date', $date->toDateString())
            ->whereIn('student_id', $students->pluck('id'))
            ->get()
            ->keyBy('student_id');

        return view('school-admin.attendance.index', [
            'students' => $students,
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
            'date' => $date,
            'className' => $className,
            'existing' => $existing,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'date' => ['required', 'date', 'before_or_equal:today'],
            'records' => ['required', 'array'],
            'records.*' => ['required', Rule::enum(AttendanceStatus::class)],
        ]);

        $date = Carbon::parse($validated['date'])->toDateString();

        $students = $school->students()->whereIn('id', array_keys($validated['records']))->get()->keyBy('id');

        $saved = 0;
        foreach ($validated['records'] as $studentId => $status) {
            $student = $students->get($studentId);

            if (! $student) {
                continue;
            }

            AttendanceRecord::updateOrCreate(
                ['student_id' => $student->id, 'date' => $date],
                [
                    'school_id' => $school->id,
                    'class_name' => $student->class_name,
                    'status' => $status,
                    'marked_by' => $request->user()->id,
                ],
            );
            $saved++;
        }

        return back()->with('status', "Attendance for {$date} saved for {$saved} student(s).");
    }

    public function history(Request $request): View
    {
        $school = $request->user()->school;

        $records = $school->attendanceRecords()
            ->with('student')
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->when($request->filled('from'), fn ($query) => $query->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('date', '<=', $request->date('to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('student', function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('date')
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.attendance.history', [
            'records' => $records,
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
        ]);
    }

    private function resolveDate(?string $date): Carbon
    {
        try {
            $parsed = $date ? Carbon::parse($date) : today();
        } catch (\Exception) {
            $parsed = today();
        }

        return $parsed->greaterThan(today()) ? today() : $parsed;
    }
}
