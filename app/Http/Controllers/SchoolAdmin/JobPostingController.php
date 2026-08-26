<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\EmploymentType;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\JobPosting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class JobPostingController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $jobs = $school->jobPostings()
            ->orderByDesc('posted_at')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.careers.index', [
            'jobs' => $jobs,
            'employmentTypes' => EmploymentType::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $job = $school->jobPostings()->create([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'posted_at' => $validated['posted_at'] ?? today(),
        ]);

        return back()->with('status', "\"{$job->title}\" was posted.");
    }

    public function update(Request $request, JobPosting $job): RedirectResponse
    {
        $this->authorizeJob($job);

        $validated = $request->validate($this->rules());

        $job->update([
            ...$validated,
            'is_active' => $request->boolean('is_active', true),
            'posted_at' => $validated['posted_at'] ?? $job->posted_at,
        ]);

        return back()->with('status', "\"{$job->title}\" was updated.");
    }

    public function destroy(JobPosting $job): RedirectResponse
    {
        $this->authorizeJob($job);

        $title = $job->title;
        $job->delete();

        return back()->with('status', "\"{$title}\" was removed.");
    }

    public function toggleActive(JobPosting $job): RedirectResponse
    {
        $this->authorizeJob($job);

        $job->update(['is_active' => ! $job->is_active]);

        return back()->with('status', $job->is_active ? "\"{$job->title}\" is now open." : "\"{$job->title}\" was closed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'title' => ['required', 'string', 'max:150'],
            'department' => ['nullable', 'string', 'max:100'],
            'employment_type' => ['required', Rule::enum(EmploymentType::class)],
            'location' => ['nullable', 'string', 'max:150'],
            'description' => ['required', 'string', 'max:5000'],
            'posted_at' => ['nullable', 'date'],
            'closes_at' => ['nullable', 'date', 'after_or_equal:posted_at'],
        ];
    }

    private function authorizeJob(JobPosting $job): void
    {
        $this->authorizeSchoolOwnership($job);
    }
}
