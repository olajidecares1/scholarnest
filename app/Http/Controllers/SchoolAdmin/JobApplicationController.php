<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\InterviewMode;
use App\Enums\JobApplicationStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\JobApplication;
use App\Models\JobApplicationDocument;
use App\Notifications\JobInterviewInvitationNotification;
use Carbon\Carbon;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Notification;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;
use Throwable;

/**
 * Reviewing applicants: read an application, move it through the recruitment
 * statuses, invite to interview, download the CV and documents.
 *
 * SCHOOL ISOLATION. Every action authorizes the APPLICATION's own school_id
 * against the signed-in admin's school first. Files are served only from here,
 * after that check, from the private disk, there is no public address for a
 * CV at all.
 */
class JobApplicationController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(JobApplicationStatus::class)],
            'job' => ['nullable', 'string', 'max:64'],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $job = filled($validated['job'] ?? null)
            ? $school->jobPostings()->where('uuid', $validated['job'])->first()
            : null;

        $applications = $school->jobApplications()
            ->with('jobPosting:id,uuid,title')
            ->when($job, fn ($q) => $q->where('job_posting_id', $job->id))
            ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->when($validated['q'] ?? null, fn ($q, string $term) => $q->where(fn ($inner) => $inner
                ->where('full_name', 'like', "%{$term}%")
                ->orWhere('email', 'like', "%{$term}%")
                ->orWhere('phone', 'like', "%{$term}%")))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.careers.applications.index', [
            'applications' => $applications,
            'jobs' => $school->jobPostings()->orderBy('title')->get(['id', 'uuid', 'title']),
            'statuses' => JobApplicationStatus::cases(),
            'filters' => $validated,
        ]);
    }

    public function show(Request $request, JobApplication $application): View
    {
        $this->authorizeSchoolOwnership($application);

        // Opening an application is reviewing it: a New one moves on, recorded.
        if ($application->status === JobApplicationStatus::New) {
            $application->moveTo(JobApplicationStatus::UnderReview, $request->user(), 'Opened for review.');
        }

        $this->markNotificationsRead($request, $application);

        return view('school-admin.careers.applications.show', [
            'application' => $application->load(['jobPosting', 'documents', 'interviews', 'events.user:id,name']),
            'statuses' => JobApplicationStatus::cases(),
            'interviewModes' => InterviewMode::cases(),
            'timezone' => $application->school->timezone ?: config('app.timezone'),
        ]);
    }

    public function updateStatus(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorizeSchoolOwnership($application);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(JobApplicationStatus::class)],
            'note' => ['nullable', 'string', 'max:500'],
        ]);

        $status = JobApplicationStatus::from($validated['status']);

        $application->moveTo($status, $request->user(), $validated['note'] ?? null);

        return back()->with('status', "{$application->full_name} is now marked \"{$status->label()}\".");
    }

    public function inviteToInterview(Request $request, JobApplication $application): RedirectResponse
    {
        $this->authorizeSchoolOwnership($application);

        $validated = $request->validate([
            'interview_date' => ['required', 'date', 'after_or_equal:today'],
            'interview_time' => ['required', 'date_format:H:i'],
            'mode' => ['required', Rule::enum(InterviewMode::class)],
            'location' => ['nullable', 'required_if:mode,'.InterviewMode::Physical->value, 'string', 'max:255'],
            'meeting_link' => ['nullable', 'required_if:mode,'.InterviewMode::Online->value, 'url:http,https', 'max:500'],
            'instructions' => ['nullable', 'string', 'max:2000'],
            'message' => ['nullable', 'string', 'max:2000'],
        ], [
            'location.required_if' => 'Enter where the interview will take place.',
            'meeting_link.required_if' => 'Enter the link to join the online interview.',
            'interview_date.after_or_equal' => 'The interview date cannot be in the past.',
        ], [
            'interview_date' => 'interview date',
            'interview_time' => 'interview time',
            'mode' => 'interview type',
            'meeting_link' => 'meeting link',
        ]);

        // The date and time the admin typed are the SCHOOL's local time.
        $timezone = $application->school->timezone ?: config('app.timezone');
        $scheduledFor = Carbon::createFromFormat('Y-m-d H:i', $validated['interview_date'].' '.$validated['interview_time'], $timezone)->utc();

        if ($scheduledFor->isPast()) {
            return back()->withErrors(['interview_time' => 'The interview time has already passed.'])->withInput();
        }

        $mode = InterviewMode::from($validated['mode']);

        $interview = DB::transaction(function () use ($application, $validated, $scheduledFor, $mode, $request) {
            $interview = $application->interviews()->create([
                'school_id' => $application->school_id,
                'scheduled_for' => $scheduledFor,
                'mode' => $mode,
                'location' => $mode === InterviewMode::Physical ? $validated['location'] : null,
                'meeting_link' => $mode === InterviewMode::Online ? $validated['meeting_link'] : null,
                'instructions' => $validated['instructions'] ?? null,
                'message' => $validated['message'] ?? null,
                'invited_by' => $request->user()->id,
            ]);

            $application->moveTo(JobApplicationStatus::InterviewInvited, $request->user(), 'Invited to interview on '.$interview->localScheduledFor()->format('j M Y, g:i A').'.');

            return $interview;
        });

        try {
            Notification::route('mail', [$application->email => $application->full_name])
                ->notify(new JobInterviewInvitationNotification($interview));

            $interview->update(['notified_at' => now()]);
        } catch (Throwable $e) {
            Log::warning('Interview invitation email could not be sent.', [
                'application' => $application->uuid,
                'reason' => $e->getMessage(),
            ]);

            return back()->with('error', "The interview was scheduled, but the invitation email to {$application->email} could not be sent. Please contact {$application->full_name} on {$application->phone}.");
        }

        return back()->with('status', "{$application->full_name} was invited to interview. The invitation was emailed to {$application->email}.");
    }

    public function downloadCv(JobApplication $application): StreamedResponse
    {
        $this->authorizeSchoolOwnership($application);

        abort_unless($application->cvExists(), 404);

        return Storage::disk(JobApplication::DISK)->download(
            $application->cv_path,
            $this->downloadName($application, $application->cv_original_name, 'CV'),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    public function downloadDocument(JobApplicationDocument $document): StreamedResponse
    {
        // The document has no school of its own; its application does.
        $application = $document->application;
        $this->authorizeSchoolOwnership($application);

        abort_unless($document->fileExists(), 404);

        return Storage::disk(JobApplication::DISK)->download(
            $document->path,
            $this->downloadName($application, $document->original_name, 'Document'),
            ['X-Content-Type-Options' => 'nosniff'],
        );
    }

    /**
     * "Ada Obi, CV.pdf", from the applicant's name and the stored extension,
     * never the uploader's own filename wholesale.
     */
    private function downloadName(JobApplication $application, string $original, string $label): string
    {
        $extension = strtolower(pathinfo($original, PATHINFO_EXTENSION));
        $extension = in_array($extension, ['pdf', 'doc', 'docx', 'jpg', 'jpeg', 'png'], true) ? $extension : 'pdf';

        $name = trim((string) preg_replace('/[^\pL\pN\s\-]+/u', '', $application->full_name)) ?: 'Applicant';

        return "{$name} {$label}.{$extension}";
    }

    private function markNotificationsRead(Request $request, JobApplication $application): void
    {
        $request->user()->unreadNotifications()
            ->where('data->application_uuid', $application->uuid)
            ->update(['read_at' => now()]);
    }
}
