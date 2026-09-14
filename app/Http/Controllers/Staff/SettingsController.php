<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Concerns\NotifiesSchoolOfProfileChanges;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ProfileChangeRequest;
use App\Models\School;
use App\Rules\UploadedImage;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use NotifiesSchoolOfProfileChanges;

    public function __construct(private readonly UploadStorage $uploads) {}

    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        return view('staff.settings.index', [
            'school' => $school,
            'staff' => $staff,
            'protectedFields' => ProfileChangeRequestController::protectedFields(),
            'changeRequests' => ProfileChangeRequest::where('school_id', $school->id)
                ->where('requester_type', 'staff')
                ->where('requester_uuid', $staff->uuid)
                ->latest()
                ->get(),
        ]);
    }

    /**
     * A teacher's own contact details.
     *
     * Their Staff ID, role, school and password are not here and are not
     * accepted from this request: those are the school's to set, and a field
     * the page does not show but the controller would still honour is not
     * read-only.
     */
    public function updateProfile(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $validated = $request->validate([
            'phone' => ['nullable', 'string', 'max:30'],
            'email' => ['nullable', 'email', 'max:255'],
            'address' => ['nullable', 'string', 'max:255'],
            'emergency_contact_name' => ['nullable', 'string', 'max:150'],
            'emergency_contact_phone' => ['nullable', 'string', 'max:30'],
        ]);

        $this->applyProfileChanges(
            $staff,
            [
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'] ?? null,
                'address' => $validated['address'] ?? null,
                'emergency_contact_name' => $validated['emergency_contact_name'] ?? null,
                'emergency_contact_phone' => $validated['emergency_contact_phone'] ?? null,
            ],
            $staff->fullName(),
            'teacher/staff',
        );

        return back()->with('status', 'Your details were updated. Your school has been notified.');
    }

    /**
     * A teacher's own signature, appended to the results they sign.
     *
     * Uploaded here and nowhere else. A signature is the one thing on a report
     * card that is supposed to mean a particular person saw it, so the school
     * office cannot upload one on a teacher's behalf, if it could, the mark
     * would prove nothing.
     *
     * The staff member is taken from the session, never from the request, so
     * there is no id to swap for a colleague's.
     */
    public function updateSignature(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $validated = $request->validate([
            'signature' => UploadedImage::rules(ImageProfile::Signature),
            'remove_signature' => ['nullable', 'boolean'],
        ]);

        if ($request->boolean('remove_signature')) {
            $staff->withdrawSignature();

            AuditLog::record(
                'signature.withdrawn',
                $staff->fullName().' withdrew their signature.',
                $staff,
                actorName: $staff->fullName(),
            );

            return back()->with('status', 'Your signature was removed. Results generated from now on will show a blank line.');
        }

        if (! isset($validated['signature'])) {
            return back()->with('status', 'Choose a signature image to upload.');
        }

        // Private, like every other signature. This upload path was the one
        // that still wrote to the public disk after the rest moved, so a
        // teacher who uploaded a photograph of their signature, rather than
        // drawing it on the pad, published it at a public address.
        $path = $this->uploads->storeImage($request->file('signature'), 'local', 'staff-signatures', ImageProfile::Signature, 'signature')->path;

        $staff->registerSignature($path);

        AuditLog::record(
            'signature.registered',
            $staff->fullName().' uploaded their signature.',
            $staff,
            actorName: $staff->fullName(),
        );

        return back()->with('status', 'Your signature was saved. It will appear on the results you sign.');
    }
}
