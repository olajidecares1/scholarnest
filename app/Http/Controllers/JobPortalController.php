<?php

namespace App\Http\Controllers;

use App\Enums\EmploymentType;
use App\Enums\PlanFeature;
use App\Models\JobPosting;
use App\Models\School;
use App\Services\Recruitment\JobApplicationSubmitter;
use App\Services\Recruitment\JobShareImage;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * A school's own Job Portal: its vacancies, each at its own address, and the
 * form to apply.
 *
 * SEPARATE FROM THE WEBSITE. The portal needs no published website - a school
 * can recruit before, or without, building one - and it carries only what an
 * applicant needs: the school's name, logo, address and contact details, and
 * its vacancies.
 *
 * SCHOOL ISOLATION. The school comes from the address (its domain, subdomain or
 * portal key), and a vacancy is only ever looked up WITHIN that school by its
 * token. A token belonging to another school answers 404 here, so a link can
 * never show one school's vacancy under another school's name, and an
 * application can never land with the wrong school.
 */
class JobPortalController extends Controller
{
    public function __construct(
        private readonly JobApplicationSubmitter $submitter,
        private readonly JobShareImage $images,
    ) {}

    public function index(Request $request, School $school): View
    {
        $this->ensureSchoolRecruits($school);

        $validated = $request->validate([
            'q' => ['nullable', 'string', 'max:100'],
            'department' => ['nullable', 'string', 'max:100'],
            'type' => ['nullable', Rule::enum(EmploymentType::class)],
        ]);

        $jobs = $school->jobPostings()
            ->open()
            ->when($validated['q'] ?? null, function ($query, string $term) {
                $query->where(fn ($q) => $q
                    ->where('title', 'like', "%{$term}%")
                    ->orWhere('department', 'like', "%{$term}%")
                    ->orWhere('location', 'like', "%{$term}%"));
            })
            ->when($validated['department'] ?? null, fn ($query, string $department) => $query->where('department', $department))
            ->when($validated['type'] ?? null, fn ($query, string $type) => $query->where('employment_type', $type))
            ->orderByDesc('published_at')
            ->paginate(12)
            ->withQueryString();

        return view('public.jobs.index', [
            'school' => $school,
            'jobs' => $jobs,
            'departments' => $school->jobPostings()->open()->whereNotNull('department')->distinct()->orderBy('department')->pluck('department'),
            'employmentTypes' => EmploymentType::cases(),
            'filters' => $validated,
        ]);
    }

    public function show(School $school, string $token): View
    {
        $job = $this->findVacancy($school, $token);

        return view('public.jobs.show', [
            'school' => $school,
            'job' => $job->load('questions'),
            'preview' => false,
        ]);
    }

    public function apply(Request $request, School $school, string $token): RedirectResponse
    {
        $job = $this->findVacancy($school, $token);

        // Read NOW, not when the form was loaded: a vacancy closed, or past its
        // deadline, while the applicant was filling it in takes nothing.
        if (! $job->acceptsApplications()) {
            return redirect()->to($job->publicUrl())
                ->with('job_application_error', 'This vacancy is no longer accepting applications.');
        }

        $job->load('questions');

        $application = $this->submitter->submit($job, $request);

        return redirect()->to($job->publicUrl().'#application')
            ->with('job_application_submitted', $application->full_name);
    }

    /**
     * The link-preview image for a vacancy - its og:image. Public, because the
     * crawlers that draw link previews on WhatsApp, Facebook and LinkedIn are
     * not signed in.
     */
    public function previewImage(School $school, string $token): Response
    {
        $job = $this->findVacancy($school, $token);

        return response($this->images->card($job), 200, [
            'Content-Type' => 'image/jpeg',
            'Cache-Control' => 'public, max-age=3600',
            'X-Content-Type-Options' => 'nosniff',
        ]);
    }

    /**
     * A vacancy of THIS school, visible to the public: published or closed.
     * A draft or archived vacancy, another school's token, or a school that
     * does not recruit on its plan all answer the same 404.
     */
    private function findVacancy(School $school, string $token): JobPosting
    {
        $this->ensureSchoolRecruits($school);

        $job = $school->jobPostings()->where('public_token', $token)->first();

        abort_if($job === null || ! $job->status->isPubliclyVisible(), 404);

        return $job->setRelation('school', $school);
    }

    private function ensureSchoolRecruits(School $school): void
    {
        abort_unless($school->is_active && $school->canUseFeature(PlanFeature::Careers), 404);
    }
}
