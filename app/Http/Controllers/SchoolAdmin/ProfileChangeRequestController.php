<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProfileChangeRequest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ProfileChangeRequestController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $requests = ProfileChangeRequest::where('school_id', $school->id)
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.profile-change-requests.index', [
            'requests' => $requests,
        ]);
    }

    public function approve(Request $request, ProfileChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeSchoolOwnership($changeRequest);
        abort_if($changeRequest->status !== 'pending', 422, 'This request has already been reviewed.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $changeRequest->approve($request->user(), $validated['review_note'] ?? null);

        AuditLog::record('profile-change-request.approved', "Approved {$changeRequest->field_label} change for {$changeRequest->subject_type} {$changeRequest->subject_uuid}.", $changeRequest);

        return back()->with('status', 'The change request was approved and applied.');
    }

    public function reject(Request $request, ProfileChangeRequest $changeRequest): RedirectResponse
    {
        $this->authorizeSchoolOwnership($changeRequest);
        abort_if($changeRequest->status !== 'pending', 422, 'This request has already been reviewed.');

        $validated = $request->validate([
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $changeRequest->reject($request->user(), $validated['review_note'] ?? null);

        AuditLog::record('profile-change-request.rejected', "Rejected {$changeRequest->field_label} change for {$changeRequest->subject_type} {$changeRequest->subject_uuid}.", $changeRequest);

        return back()->with('status', 'The change request was rejected.');
    }
}
