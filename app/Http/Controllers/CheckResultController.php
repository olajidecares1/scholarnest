<?php

namespace App\Http\Controllers;

use App\Enums\ResultCheckingPinStatus;
use App\Http\Requests\VerifyResultPinRequest;
use App\Models\ResultCheckingPin;
use App\Models\ResultCheckingPinUsage;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * A WAEC-scratch-card-style result checker: deliberately independent of the
 * front-facing website (PublicSchoolWebsiteController / website_access),
 * since it must keep working for Basic-plan schools, which are barred from
 * having a published website and have no student/parent portal at all.
 */
class CheckResultController extends Controller
{
    public function create(School $school): View
    {
        return view('check-result.show', ['school' => $school]);
    }

    public function verify(VerifyResultPinRequest $request, School $school): RedirectResponse
    {
        $request->ensureIsNotRateLimited($school);

        $code = strtoupper($request->string('code')->toString());
        $admissionNumber = $request->string('admission_number')->toString();

        try {
            $usage = DB::transaction(function () use ($school, $code, $admissionNumber) {
                $pin = ResultCheckingPin::where('school_id', $school->id)
                    ->where('code', $code)
                    ->lockForUpdate()
                    ->first();

                if (! $pin || $pin->status === ResultCheckingPinStatus::Revoked) {
                    throw ValidationException::withMessages(['code' => 'This PIN is not valid.']);
                }

                if ($pin->examination_id === null) {
                    throw ValidationException::withMessages(['code' => 'This PIN has not yet been activated by your school. Please contact them.']);
                }

                if ($pin->status === ResultCheckingPinStatus::Exhausted || $pin->uses_count >= $pin->max_uses) {
                    throw ValidationException::withMessages(['code' => 'This result-checking PIN has reached its maximum usage limit.']);
                }

                $student = Student::where('school_id', $school->id)
                    ->where('admission_number', $admissionNumber)
                    ->first();

                if (! $student) {
                    throw ValidationException::withMessages(['admission_number' => 'No student was found with that admission number.']);
                }

                if ($pin->bound_student_id !== null && $pin->bound_student_id !== $student->id) {
                    throw ValidationException::withMessages(['code' => 'This PIN is not valid for this student.']);
                }

                $pin->uses_count++;

                $pin->update([
                    'bound_student_id' => $pin->bound_student_id ?? $student->id,
                    'uses_count' => $pin->uses_count,
                    'status' => $pin->uses_count >= $pin->max_uses
                        ? ResultCheckingPinStatus::Exhausted
                        : ResultCheckingPinStatus::Active,
                ]);

                return ResultCheckingPinUsage::create([
                    'result_checking_pin_id' => $pin->id,
                    'student_id' => $student->id,
                    'examination_id' => $pin->examination_id,
                    'ip_address' => request()->ip(),
                    'used_at' => now(),
                ]);
            });
        } catch (ValidationException $exception) {
            $request->hit($school);

            throw $exception;
        }

        $request->clearLimiter($school);

        return redirect()->route('check-result.result', ['school' => $school, 'usage' => $usage]);
    }

    public function result(School $school, ResultCheckingPinUsage $usage): View
    {
        abort_unless($usage->pin->school_id === $school->id, 404);

        $examination = $usage->examination;
        $student = $usage->student;
        $subjects = $examination->subjects()->with(['scores' => fn ($query) => $query->where('student_id', $student->id)])->get();

        return view('check-result.result', [
            'school' => $school,
            'examination' => $examination,
            'student' => $student,
            'subjects' => $subjects,
            'usage' => $usage,
        ]);
    }
}
