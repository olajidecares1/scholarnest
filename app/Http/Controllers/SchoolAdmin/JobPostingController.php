<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\EmploymentType;
use App\Enums\JobApplicationStatus;
use App\Enums\JobPostingStatus;
use App\Enums\JobQuestionType;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use App\Services\Recruitment\JobPostingEditor;
use App\Services\Recruitment\JobShareImage;
use App\Services\Uploads\UploadStorage;
use App\Support\SchoolContact;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A school's vacancies: create, edit, preview, publish, close, reopen, extend,
 * archive, share.
 *
 * Every action that names a vacancy checks it belongs to the signed-in admin's
 * school before anything else happens.
 */
class JobPostingController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(private readonly JobPostingEditor $editor) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(JobPostingStatus::class)],
            'q' => ['nullable', 'string', 'max:100'],
        ]);

        $status = isset($validated['status']) ? JobPostingStatus::from($validated['status']) : null;

        $jobs = $school->jobPostings()
            ->withCount([
                'applications',
                'applications as new_applications_count' => fn ($q) => $q->where('status', JobApplicationStatus::New),
            ])
            // Archived vacancies stay out of the way unless asked for.
            ->when($status, fn ($q) => $q->where('status', $status), fn ($q) => $q->where('status', '!=', JobPostingStatus::Archived))
            ->when($validated['q'] ?? null, fn ($q, string $term) => $q->where('title', 'like', "%{$term}%"))
            ->latest()
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.careers.index', [
            'school' => $school,
            'jobs' => $jobs,
            'filters' => $validated,
            'statuses' => JobPostingStatus::cases(),
            'stats' => [
                'open' => $school->jobPostings()->open()->count(),
                'applications' => $school->jobApplications()->count(),
                'new' => $school->jobApplications()->where('status', JobApplicationStatus::New)->count(),
                'interviews' => $school->jobApplications()->where('status', JobApplicationStatus::InterviewInvited)->count(),
            ],
        ]);
    }

    public function create(Request $request): View
    {
        return $this->form($request, new JobPosting([
            'employment_type' => EmploymentType::FullTime,
            'closes_at' => today()->addWeeks(3),
        ]));
    }

    public function store(Request $request): RedirectResponse
    {
        $job = $this->editor->create($request->user()->school, $request);

        if ($request->input('intent') === 'publish') {
            return $this->publish($job, 'created and published');
        }

        return redirect()->route('careers.show', $job)->with('status', "\"{$job->title}\" was saved as a draft. Preview it, then publish when it is ready.");
    }

    public function show(Request $request, JobPosting $job): View
    {
        $this->authorizeSchoolOwnership($job);

        $validated = $request->validate([
            'status' => ['nullable', Rule::enum(JobApplicationStatus::class)],
        ]);

        $applications = $job->applications()
            ->when($validated['status'] ?? null, fn ($q, string $status) => $q->where('status', $status))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.careers.show', [
            'job' => $job->loadCount('applications')->load('questions'),
            'applications' => $applications,
            'statusCounts' => $job->applications()->selectRaw('status, count(*) as total')->groupBy('status')->pluck('total', 'status'),
            'applicationStatuses' => JobApplicationStatus::cases(),
            'filters' => $validated,
        ]);
    }

    public function edit(Request $request, JobPosting $job): View
    {
        $this->authorizeSchoolOwnership($job);

        return $this->form($request, $job->load('questions'));
    }

    public function update(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        $job = $this->editor->update($job, $request);

        if ($request->input('intent') === 'publish' && $job->status !== JobPostingStatus::Published) {
            return $this->publish($job, 'updated and published');
        }

        return redirect()->route('careers.show', $job)->with('status', "\"{$job->title}\" was updated.");
    }

    /**
     * The vacancy exactly as an applicant will see it, drafts included, with
     * the application form shown but not submittable.
     */
    public function preview(JobPosting $job): View
    {
        $this->authorizeSchoolOwnership($job);

        return view('public.jobs.show', [
            'school' => $job->school,
            'job' => $job->load('questions'),
            'preview' => true,
        ]);
    }

    /**
     * The share image, for posting to Instagram, WhatsApp Status and the rest.
     */
    public function shareImage(JobPosting $job, JobShareImage $images): Response
    {
        $this->authorizeSchoolOwnership($job);

        return response($images->story($job), 200, [
            'Content-Type' => 'image/jpeg',
            'Content-Disposition' => 'attachment; filename="'.$images->filename($job).'"',
            'Cache-Control' => 'private, no-store',
        ]);
    }

    public function publish(JobPosting $job, string $done = 'published'): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        if ($job->deadlinePassed()) {
            return redirect()->route('careers.show', $job)
                ->with('error', 'The application deadline has already passed. Set a new deadline before publishing.');
        }

        $job->update([
            'status' => JobPostingStatus::Published,
            'published_at' => $job->published_at ?? now(),
            'closed_at' => null,
            'archived_at' => null,
        ]);

        return redirect()->route('careers.show', $job)->with('status', "\"{$job->title}\" was {$done}. It is live on your Job Portal. Share its link to start receiving applications.");
    }

    public function unpublish(JobPosting $job): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        $job->update(['status' => JobPostingStatus::Draft]);

        return back()->with('status', "\"{$job->title}\" was taken off your Job Portal and is a draft again.");
    }

    public function close(JobPosting $job): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        $job->update(['status' => JobPostingStatus::Closed, 'closed_at' => now()]);

        return back()->with('status', "\"{$job->title}\" is closed. Its link still works, but it no longer accepts applications.");
    }

    public function reopen(JobPosting $job): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        return $this->publish($job, 'reopened');
    }

    public function extendDeadline(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        $validated = $request->validate(
            ['closes_at' => ['required', 'date', 'after_or_equal:today']],
            ['closes_at.after_or_equal' => 'The new deadline cannot be in the past.'],
            ['closes_at' => 'new deadline'],
        );

        $job->update(['closes_at' => $validated['closes_at']]);

        return back()->with('status', "Applications for \"{$job->title}\" now close on {$job->closes_at->format('j F Y')}.");
    }

    public function archive(JobPosting $job): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        $job->update(['status' => JobPostingStatus::Archived, 'archived_at' => now()]);

        return redirect()->route('careers.index')->with('status', "\"{$job->title}\" was archived. Its applications are kept.");
    }

    /**
     * Deleting is for vacancies nobody has applied to. One with applications is
     * archived instead, so no applicant's details or CV disappear with it.
     */
    public function destroy(JobPosting $job, UploadStorage $uploads): RedirectResponse
    {
        $this->authorizeSchoolOwnership($job);

        if ($job->applications()->exists()) {
            return back()->with('error', "\"{$job->title}\" has applications, so it cannot be deleted. Archive it instead and the applications are kept.");
        }

        $title = $job->title;
        $uploads->delete('public', $job->featured_image_path);
        $job->delete();

        return redirect()->route('careers.index')->with('status', "\"{$title}\" was deleted.");
    }

    private function form(Request $request, JobPosting $job): View
    {
        $school = $request->user()->school;

        return view('school-admin.careers.form', [
            'job' => $job,
            'school' => $school,
            'contact' => SchoolContact::for($school),
            'employmentTypes' => EmploymentType::cases(),
            'questionTypes' => JobQuestionType::cases(),
            'maxQuestions' => JobPostingEditor::MAX_QUESTIONS,
        ]);
    }
}
