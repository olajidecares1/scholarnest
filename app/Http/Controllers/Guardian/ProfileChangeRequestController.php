<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Controller;
use App\Http\Controllers\Student\ProfileChangeRequestController as StudentProfileChangeRequestController;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use App\Models\Student;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileChangeRequestController extends Controller
{
    public function store(Request $request, School $school, Student $student): RedirectResponse
    {
        $guardian = $request->user('guardian');

        abort_unless($guardian->students()->where('students.id', $student->id)->exists(), 403);

        $fields = StudentProfileChangeRequestController::protectedFields();

        $validated = $request->validate([
            'field_key' => ['required', Rule::in(array_keys($fields))],
            'requested_value' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        ProfileChangeRequest::create([
            'school_id' => $school->id,
            'requester_type' => 'guardian',
            'requester_uuid' => $guardian->uuid,
            'subject_type' => 'student',
            'subject_uuid' => $student->uuid,
            'field_key' => $validated['field_key'],
            'field_label' => $fields[$validated['field_key']],
            'current_value' => $student->{$validated['field_key']},
            'requested_value' => $validated['requested_value'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Your change request has been submitted for the school office to review.');
    }
}
