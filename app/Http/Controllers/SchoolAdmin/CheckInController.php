<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\AttendanceMode;
use App\Http\Controllers\Controller;
use App\Models\StaffAttendanceRecord;
use App\Services\Attendance\CheckInPoster;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * QR check-in, from the office's side: switch it on, say where the school is,
 * print the poster, and read what the poster has been recording.
 */
class CheckInController extends Controller
{
    public function __construct(private readonly CheckInPoster $poster) {}

    public function edit(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.attendance.check-in', [
            'school' => $school,
            'modes' => AttendanceMode::cases(),
            'posterUrl' => $school->check_in_token ? $this->poster->url($school) : null,
            'recentScans' => $school->attendanceScans()
                ->with(['student', 'staff'])
                ->latest('scanned_at')
                ->limit(25)
                ->get(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'check_in_enabled' => ['nullable', 'boolean'],
            'student_attendance_mode' => ['required', Rule::enum(AttendanceMode::class)],
            'latitude' => ['nullable', 'numeric', 'between:-90,90'],
            'longitude' => ['nullable', 'numeric', 'between:-180,180'],
            'check_in_radius_metres' => ['required', 'integer', 'min:25', 'max:2000'],
        ]);

        $enabled = $request->boolean('check_in_enabled');

        // WITHOUT COORDINATES THERE IS NO CHECK-IN, and this is the one place
        // that can say so. A fixed QR code that anybody can photograph is only
        // evidence of attendance because a scan has to come from the school's
        // own ground; switching it on with nowhere to measure from would turn
        // the register into a list of people who own a phone.
        if ($enabled && ($validated['latitude'] === null || $validated['longitude'] === null)) {
            throw ValidationException::withMessages([
                'latitude' => 'Set the school\'s location before switching check-in on. A scan is only counted near the school, and this is what it is measured from.',
            ]);
        }

        $school->fill([
            'check_in_enabled' => $enabled,
            'student_attendance_mode' => $validated['student_attendance_mode'],
            'latitude' => $validated['latitude'],
            'longitude' => $validated['longitude'],
            'check_in_radius_metres' => $validated['check_in_radius_metres'],
        ]);

        // A school switching this on for the first time has no poster yet.
        if ($enabled && ! $school->check_in_token) {
            $school->save();
            $this->poster->issueToken($school);

            return back()->with('status', 'Check-in is on. Print the poster below and put it up at the gate.');
        }

        $school->save();

        return back()->with('status', 'Check-in settings saved.');
    }

    /**
     * Retire the poster on the wall and issue a new one.
     */
    public function rotate(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $this->poster->issueToken($school);

        return back()->with('status', 'A new code has been issued. The old poster has stopped working, so print and put up the new one.');
    }

    public function poster(Request $request): Response
    {
        $school = $request->user()->school;

        abort_unless($school->check_in_token, 404);

        return $this->poster->document($school)->download($this->poster->filename($school));
    }

    /**
     * The staff register: who was in, when they arrived and when they left.
     */
    public function staff(Request $request): View
    {
        $school = $request->user()->school;

        $records = StaffAttendanceRecord::where('school_id', $school->id)
            ->with('staff')
            ->when($request->filled('from'), fn ($query) => $query->whereDate('date', '>=', $request->date('from')))
            ->when($request->filled('to'), fn ($query) => $query->whereDate('date', '<=', $request->date('to')))
            ->when($request->filled('search'), function ($query) use ($request) {
                $search = $request->string('search');
                $query->whereHas('staff', function ($query) use ($search) {
                    $query->where('first_name', 'like', "%{$search}%")
                        ->orWhere('last_name', 'like', "%{$search}%");
                });
            })
            ->orderByDesc('date')
            ->orderBy('arrived_at')
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.attendance.staff', [
            'records' => $records,
        ]);
    }
}
