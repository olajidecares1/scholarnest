<?php

namespace App\Http\Controllers\Staff\Cbt;

use App\Enums\CbtTestStatus;
use App\Http\Controllers\Controller;
use App\Models\CbtTest;
use App\Models\School;
use App\Services\CbtExtractionAvailability;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TestController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        return view('staff.cbt.index', [
            'school' => $school,
            'tests' => $staff->cbtTests()->withCount('questions')->latest()->get(),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');
        $validated = $this->validated($request);

        $test = $staff->cbtTests()->create([
            ...collect($validated)->except('shuffle_questions')->all(),
            'shuffle_questions' => $request->boolean('shuffle_questions'),
            'school_id' => $staff->school_id,
            'status' => CbtTestStatus::Draft,
        ]);

        // Straight to the upload panel. A teacher who came here to turn a
        // Word paper into a CBT should not have to hunt for the uploader on
        // the page they have just been dropped onto.
        return redirect()
            ->to(route('staff.cbt.tests.show', [$school, $test]).'#document-upload')
            ->with('status', "\"{$test->title}\" was created. Upload your question paper below.");
    }

    public function show(Request $request, School $school, CbtTest $test, CbtExtractionAvailability $availability): View
    {
        $this->authorizeTest($request, $test);

        $test->load(['questions.options', 'documentUploads' => fn ($query) => $query->latest()]);

        return view('staff.cbt.show', [
            'extractionWarning' => $availability->warning(),
            'publishBlocker' => $test->publishBlocker(),
            'school' => $school,
            'test' => $test,
        ]);
    }

    public function update(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($request, $test);

        $validated = $this->validated($request);

        $test->update([
            ...collect($validated)->except('shuffle_questions')->all(),
            'shuffle_questions' => $request->boolean('shuffle_questions'),
        ]);

        return back()->with('status', "\"{$test->title}\" was updated.");
    }

    public function destroy(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($request, $test);

        $title = $test->title;
        $test->delete();

        return redirect()->route('staff.cbt.tests.index', $school)->with('status', "\"{$title}\" was deleted.");
    }

    public function updateStatus(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($request, $test);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(CbtTestStatus::class)],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
        ]);

        $newStatus = CbtTestStatus::from($validated['status']);

        if ($newStatus === CbtTestStatus::Published) {
            // 422 rather than a redirect, matching how the lock rule below
            // reports a state the request cannot have.
            abort_if((bool) ($blocker = $test->publishBlocker()), 422, (string) $blocker);
        }

        if (in_array($newStatus, [CbtTestStatus::Draft, CbtTestStatus::Locked], true) && $test->hasStudentAttempts()) {
            abort(422, 'This test cannot be locked or unlocked because students have already started it. You can still archive it.');
        }

        $test->update([
            'status' => $newStatus,
            'available_from' => $validated['available_from'] ?? null,
            'available_until' => $validated['available_until'] ?? null,
        ]);

        return back()->with('status', "\"{$test->title}\" is now {$test->status->label()}.");
    }

    public function duplicate(Request $request, School $school, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($request, $test);

        $test->load('questions.options');

        $copy = $test->staff->cbtTests()->create([
            'school_id' => $test->school_id,
            'title' => "{$test->title} (Copy)",
            'subject' => $test->subject,
            'class_name' => $test->class_name,
            'session' => $test->session,
            'duration_minutes' => $test->duration_minutes,
            'pass_mark' => $test->pass_mark,
            'shuffle_questions' => $test->shuffle_questions,
            'status' => CbtTestStatus::Draft,
        ]);

        foreach ($test->questions as $question) {
            $newQuestion = $copy->questions()->create([
                'question_text' => $question->question_text,
                'image_path' => $question->image_path,
                'sort_order' => $question->sort_order,
            ]);

            foreach ($question->options as $option) {
                $newQuestion->options()->create([
                    'label' => $option->label,
                    'option_text' => $option->option_text,
                    'is_correct' => $option->is_correct,
                ]);
            }
        }

        return redirect()->route('staff.cbt.tests.show', [$school, $copy])->with('status', "Duplicated as \"{$copy->title}\".");
    }

    /**
     * @return array<string, mixed>
     */
    private function validated(Request $request): array
    {
        return $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:100'],
            'class_name' => ['required', 'string', 'max:50'],
            'session' => ['nullable', 'string', 'max:50'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'pass_mark' => ['required', 'integer', 'min:1', 'max:100'],
            'shuffle_questions' => ['nullable', 'boolean'],
        ]);
    }

    private function authorizeTest(Request $request, CbtTest $test): void
    {
        abort_unless($test->staff_id === $request->user('staff')->id, 403);
    }
}
