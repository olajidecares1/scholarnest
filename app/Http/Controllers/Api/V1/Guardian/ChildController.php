<?php

namespace App\Http\Controllers\Api\V1\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\AttendanceRecordResource;
use App\Http\Resources\Api\V1\StudentResource;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;

class ChildController extends Controller
{
    /**
     * The children this guardian is linked to.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $guardian = $request->user();

        return StudentResource::collection(
            $guardian->students()->where('students.school_id', $guardian->school_id)->get()
        );
    }

    public function show(Request $request, Student $student): StudentResource
    {
        return new StudentResource($this->authorizeChild($request, $student));
    }

    public function attendance(Request $request, Student $student): AnonymousResourceCollection
    {
        $student = $this->authorizeChild($request, $student);

        $records = $student->attendanceRecords()
            ->where('school_id', $student->school_id)
            ->when($request->date('from'), fn ($query, $from) => $query->where('date', '>=', $from))
            ->when($request->date('to'), fn ($query, $to) => $query->where('date', '<=', $to))
            ->orderByDesc('date')
            ->paginate($request->integer('per_page') ?: 50);

        return AttendanceRecordResource::collection($records);
    }

    /**
     * Refuse a child who is not this guardian's.
     *
     * The link, not the school. Two children at the same school are both
     * "in this tenant", and a guardian may see exactly the ones they are
     * recorded against, so the school check is necessary and nowhere near
     * sufficient, and both are made here rather than at three call sites.
     */
    private function authorizeChild(Request $request, Student $student): Student
    {
        $guardian = $request->user();

        abort_unless($student->school_id === $guardian->school_id, 404);
        abort_unless($guardian->students()->whereKey($student->getKey())->exists(), 404);

        return $student;
    }
}
