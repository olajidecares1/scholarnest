<?php

namespace App\Http\Controllers\Api\V1\Student;

use App\Http\Controllers\Controller;
use App\Http\Resources\Api\V1\ExaminationResource;
use App\Http\Resources\Api\V1\ResultResource;
use App\Models\Examination;
use App\Models\Student;
use App\Services\ReportCardData;
use App\Services\ResultAccessPolicy;
use App\Services\ResultTokenVerifier;
use Illuminate\Http\Request;
use Illuminate\Http\Resources\Json\AnonymousResourceCollection;
use Illuminate\Validation\ValidationException;

/**
 * A student's own results.
 *
 * The web portal remembers an unlocked result in the session. There is no
 * session here, so the exam token is presented with the request that wants the
 * result and redeemed then and there. That is a real difference in feel, a
 * client must ask for the token each time rather than once per visit, and it
 * is the honest way to do it statelessly: the alternative is inventing a
 * second, longer-lived unlock credential, which is a new thing to steal.
 *
 * Both gates apply, in the same order as the portal. Fees first, because a
 * token does not buy a result the school is withholding over money, and
 * checking it the other way round would spend a token on a result that stays
 * shut anyway.
 */
class ResultController extends Controller
{
    /**
     * Every examination this student has a result in.
     *
     * No scores. The list says what exists and whether it can be opened; the
     * detail endpoint below is the only thing that serialises a mark.
     */
    public function index(Request $request): AnonymousResourceCollection
    {
        $student = $request->user();

        $examinations = Examination::query()
            ->where('school_id', $student->school_id)
            ->where('class_name', $student->class_name)
            ->whereHas('subjects.scores', fn ($query) => $query->where('student_id', $student->id))
            ->orderByDesc('exam_date')
            ->get();

        return ExaminationResource::collection(
            $examinations->map(fn (Examination $examination) => (new ExaminationResource($examination))->forStudent($student))
        );
    }

    /**
     * One report card, in exchange for its exam token.
     *
     * @throws ValidationException
     */
    public function show(Request $request, Examination $examination): ResultResource
    {
        $student = $request->user();

        $this->assertExaminationIsTheirs($student, $examination);
        $this->assertNotWithheld($student, $examination);
        $this->redeemToken($request, $student, $examination);

        return new ResultResource(ReportCardData::for($examination, $student));
    }

    private function assertExaminationIsTheirs(Student $student, Examination $examination): void
    {
        abort_unless($examination->school_id === $student->school_id, 404);
        abort_unless($examination->class_name === $student->class_name, 404);
    }

    private function assertNotWithheld(Student $student, Examination $examination): void
    {
        $policy = app(ResultAccessPolicy::class);

        abort_if(
            $policy->isLocked($student, $examination),
            403,
            $policy->lockMessage($student, $examination)
                ?? 'This result is on hold until the outstanding school-fee balance is settled.',
        );
    }

    /**
     * @throws ValidationException
     */
    private function redeemToken(Request $request, Student $student, Examination $examination): void
    {
        $validated = $request->validate([
            'exam_token' => ['required', 'string', 'min:8', 'max:64'],
        ], [
            'exam_token.required' => 'Send the exam token your school gave you.',
        ]);

        $usage = app(ResultTokenVerifier::class)->verify(
            $student->school,
            $validated['exam_token'],
            $request,
            $student,
            $examination,
            $student,
        );

        if ($usage !== null) {
            return;
        }

        // One message for every kind of failure, as everywhere else a token is
        // checked. Saying which check failed would tell somebody holding a
        // token whether it is real.
        throw ValidationException::withMessages([
            'exam_token' => ResultTokenVerifier::GENERIC_FAILURE_MESSAGE,
        ]);
    }
}
