<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Concerns\UnlocksResultsWithToken;
use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\ProfileChangeRequestController as StudentProfileChangeRequestController;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ChildProfileController extends Controller
{
    use UnlocksResultsWithToken;

    public function show(Request $request, School $school, Student $student): View
    {
        $this->authorizeChild($request, $student);

        $guardian = $request->user('guardian');

        return view('guardian.children.profile', [
            'school' => $school,
            'activeChild' => $student,
            'student' => $student,

            // For the Check Result card: how many of this child's results are
            // open, and how many still want their token.
            'resultLocks' => $this->resultLockSummary($student),

            'protectedFields' => StudentProfileChangeRequestController::protectedFields(),
            'changeRequests' => ProfileChangeRequest::where('school_id', $school->id)
                ->where('requester_type', 'guardian')
                ->where('requester_uuid', $guardian->uuid)
                ->where('subject_uuid', $student->uuid)
                ->latest()
                ->get(),
        ]);
    }

    private function authorizeChild(Request $request, Student $student): void
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);
    }
}
