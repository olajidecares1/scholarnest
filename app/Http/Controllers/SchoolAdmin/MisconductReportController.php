<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\MisconductReport;
use App\Models\MisconductReportAttachment;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Where a school reads what the public has sent it.
 *
 * Every read is scoped to the acting school, and the attachments are the
 * reason that matters more here than almost anywhere else: these are
 * photographs and video of somebody's child, sent by a stranger. They live on
 * the private disk and are served only through download(), which checks the
 * file belongs to this school before it opens it.
 */
class MisconductReportController extends Controller
{
    use AuthorizesSchoolOwnership;

    /**
     * Record what the school decided.
     */
    public function update(Request $request, MisconductReport $misconductReport): RedirectResponse
    {
        $this->authorizeSchoolOwnership($misconductReport);

        $validated = $request->validate([
            'status' => ['required', Rule::in([MisconductReport::STATUS_NEW, MisconductReport::STATUS_REVIEWED, MisconductReport::STATUS_DISMISSED])],
            'review_note' => ['nullable', 'string', 'max:1000'],
        ]);

        $misconductReport->update([
            ...$validated,

            // Who closed it and when. A report that changed status with no
            // name against it is a decision nobody made.
            'reviewed_by' => $validated['status'] === MisconductReport::STATUS_NEW ? null : $request->user()->id,
            'reviewed_at' => $validated['status'] === MisconductReport::STATUS_NEW ? null : now(),
        ]);

        return back()->with('status', 'The report was updated.');
    }

    /**
     * Serve one attachment.
     *
     * The ownership check is on the REPORT the file hangs off, not on the file
     * - an attachment has no school of its own, and checking the thing that
     * does is what stops one school opening another's evidence by uuid.
     */
    public function download(Request $request, MisconductReportAttachment $attachment): StreamedResponse
    {
        $this->authorizeSchoolOwnership($attachment->report);

        abort_unless($attachment->exists(), 404);

        // Inline, so a photograph opens and a video plays rather than landing
        // in a downloads folder. The filename is the reporter's own, sanitised
        // by the response helper.
        return Storage::disk('local')->response(
            $attachment->path,
            $attachment->original_name,
            ['Content-Type' => $attachment->mime_type],
        );
    }
}
