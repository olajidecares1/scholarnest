<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

class ProfileChangeRequestController extends Controller
{
    /**
     * @return array<string, string>
     */
    public static function protectedFields(): array
    {
        return [
            'staff_number' => 'Staff ID',
            'department' => 'Department',
        ];
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $validated = $request->validate([
            'field_key' => ['required', Rule::in(array_keys(self::protectedFields()))],
            'requested_value' => ['required', 'string', 'max:255'],
            'reason' => ['nullable', 'string', 'max:1000'],
        ]);

        ProfileChangeRequest::create([
            'school_id' => $school->id,
            'requester_type' => 'staff',
            'requester_uuid' => $staff->uuid,
            'subject_type' => 'staff',
            'subject_uuid' => $staff->uuid,
            'field_key' => $validated['field_key'],
            'field_label' => self::protectedFields()[$validated['field_key']],
            'current_value' => $staff->{$validated['field_key']},
            'requested_value' => $validated['requested_value'],
            'reason' => $validated['reason'] ?? null,
            'status' => 'pending',
        ]);

        return back()->with('status', 'Your change request has been submitted for the school office to review.');
    }
}
