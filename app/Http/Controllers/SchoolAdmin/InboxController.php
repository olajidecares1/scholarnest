<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\ContactMessage;
use App\Models\MisconductReport;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Everything the public has sent the school, in one place.
 *
 * Two kinds of thing arrive from the website, an enquiry and a report of a
 * pupil's conduct, and they land in the same inbox because that is where an
 * administrator will look for either. They stay separate underneath: a
 * conduct report carries photographs of a child and is read by a different
 * part of the brain than "what are your fees?".
 */
class InboxController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.inbox.index', [
            'tab' => $request->string('tab')->toString() === 'reports' ? 'reports' : 'messages',

            'messages' => ContactMessage::query()
                ->where('school_id', $school->id)
                ->with('reader')
                ->latest()
                ->paginate(10, ['*'], 'messages')
                ->withQueryString(),

            'reports' => MisconductReport::query()
                ->where('school_id', $school->id)
                ->with(['attachments', 'reviewer'])
                ->latest()
                ->paginate(10, ['*'], 'reports')
                ->withQueryString(),

            'unreadMessages' => ContactMessage::where('school_id', $school->id)->whereNull('read_at')->count(),
            'newReports' => MisconductReport::where('school_id', $school->id)->where('status', MisconductReport::STATUS_NEW)->count(),
        ]);
    }

    /**
     * One enquiry, in full.
     *
     * Opening it marks it read, which is what an administrator means by
     * opening it, the button on the listing stays for marking it back to
     * unread, or for clearing one without reading it.
     */
    public function showMessage(Request $request, ContactMessage $contactMessage): View
    {
        $this->authorizeSchoolOwnership($contactMessage);

        if ($contactMessage->isUnread()) {
            $contactMessage->update(['read_at' => now(), 'read_by' => $request->user()->id]);
        }

        return view('school-admin.inbox.message', [
            'message' => $contactMessage->load('reader'),
        ]);
    }

    /**
     * One conduct report, in full, with its photographs and the review form.
     *
     * A report is NOT marked reviewed by being opened. "Reviewed" here means an
     * administrator decided something, and that is what the form on this page
     * records, reading is not deciding.
     */
    public function showReport(MisconductReport $misconductReport): View
    {
        $this->authorizeSchoolOwnership($misconductReport);

        return view('school-admin.inbox.report', [
            'report' => $misconductReport->load(['attachments', 'reviewer']),
        ]);
    }

    /**
     * Mark one enquiry as read, and record who read it.
     */
    public function readMessage(Request $request, ContactMessage $contactMessage): RedirectResponse
    {
        $this->authorizeSchoolOwnership($contactMessage);

        $contactMessage->update([
            'read_at' => $contactMessage->isUnread() ? now() : null,
            'read_by' => $contactMessage->isUnread() ? $request->user()->id : null,
        ]);

        return back();
    }
}
