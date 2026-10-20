<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Http\Resources\Api\V1\AttendanceSummaryResource;
use App\Services\Attendance\TermAttendance;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    /**
     * This student's own attendance, most recent first.
     *
     * Scoped by school as well as by student. Filtering by student already
     * confines it, a student belongs to one school, but stating it makes
     * that a property of the query rather than a consequence of an invariant
     * kept somewhere else.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user();

        $records = $student->attendanceRecords()
            ->where('school_id', $student->school_id)
            ->when($request->date('from'), fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->where('date', '<=', $to))
            ->orderByDesc('date')
            ->paginate($request->integer('per_page') ?: 50);

        return AttendanceRecordResource::collection($records);
    }

    /**
     * This student's term totals, where the school enters attendance as day
     * counts. An empty list, with manual_attendance_enabled false, at a
     * school that does not.
     */
    public function terms(Request $request, TermAttendance $attendance): AnonymousResourceCollection
    {
        $student = $request->user();

        return AttendanceSummaryResource::collection($attendance->totalsForStudent($student))
            ->additional(['meta' => ['manual_attendance_enabled' => (bool) $student->school?->usesManualAttendance()]]);
    }
}
