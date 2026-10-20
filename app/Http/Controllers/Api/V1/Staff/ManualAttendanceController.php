<?php

namespace App\Http\Controllers\Api\V1\Staff;

use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\AcademicTerm;
use App\Models\AttendanceSummary;
use App\Services\ManualAttendanceSheet;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/**
 * Manual attendance for the mobile app: a class teacher's term totals, read
 * and saved through the same ManualAttendanceSheet the web portal uses.
 *
 * Behind the manual_attendance middleware, so a school whose Super Admin has
 * switched the feature off gets a 403 with code "feature_disabled" here, never
 * the data and never a save.
 */
class ManualAttendanceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $staff = $request->user();
        $school = $staff->school;
        $classes = $staff->classesAsClassTeacher();

        $requested = $request->string('class')->toString();
        $className = $requested && $classes->contains($requested) ? $requested : $classes->first();
        [$session, $term] = ManualAttendanceSheet::periodFrom($request, $school);
        $schoolDays = AcademicTerm::rangeFor($school, $session, $term)?->school_days;

        $students = $className
            ? $school->students()->where('is_active', true)->where('class_name', $className)->orderBy('last_name')->get()
            : collect();

        $rows = ManualAttendanceSheet::rows($school, $students, $session, $term, $request->boolean('prefill'));

        return response()->json([
            'data' => $rows->map(fn (array $row) => [
                'student' => [
                    'id' => $row['student']->uuid,
                    'name' => $row['student']->fullName(),
                ],
                'days' => $row['values'],
                'total' => array_sum($row['values']),
                'discrepancy' => AttendanceSummary::discrepancyBetween(array_sum($row['values']), $schoolDays),
                'saved' => $row['saved'],
                'from_register' => $row['fromRegister'],
                'locked' => $row['locked'],
            ])->values(),
            'meta' => [
                'classes' => $classes->values(),
                'class_name' => $className,
                'session' => $session,
                'term' => $term->value,
                'school_days' => $schoolDays,
            ],
        ]);
    }

    public function store(Request $request): JsonResponse
    {
        $staff = $request->user();
        $school = $staff->school;
        $classes = $staff->classesAsClassTeacher();

        // Keyed by student uuid on the API, the only id this API exposes.
        $validated = $request->validate([
            'class_name' => ['required', 'string', Rule::in($classes)],
            ...ManualAttendanceSheet::rules(),
        ]);

        $term = ExamTerm::from($validated['term']);

        $students = $school->students()
            ->where('class_name', $validated['class_name'])
            ->whereIn('uuid', array_keys($validated['entries']))
            ->get();

        $entries = $students->mapWithKeys(fn ($student) => [$student->id => $validated['entries'][$student->uuid]])->all();

        $result = ManualAttendanceSheet::save($school, $students->keyBy('id'), $validated['session'], $term, $entries, staff: $staff, respectLocks: true);

        return response()->json([
            'message' => ManualAttendanceSheet::statusMessage($result, $term, $validated['session']),
            'data' => $result,
        ]);
    }
}
