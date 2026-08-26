<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class AttendanceController extends Controller
{
    /**
     * This student's own attendance, most recent first.
     *
     * Scoped by school as well as by student. Filtering by student already
     * confines it - a student belongs to one school - but stating it makes
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
}
