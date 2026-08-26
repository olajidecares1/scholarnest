<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\CbtTestStatus;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\CbtTest;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CbtTestController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.cbt-tests.index', [
            'tests' => CbtTest::where('school_id', $school->id)
                ->with('staff')
                ->withCount(['questions', 'attempts'])
                ->latest()
                ->get(),
        ]);
    }

    public function show(CbtTest $test): View
    {
        $this->authorizeTest($test);

        $test->load(['staff', 'questions.options', 'attempts.student']);

        return view('school-admin.cbt-tests.show', [
            'test' => $test,
        ]);
    }

    public function update(Request $request, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($test);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subject' => ['required', 'string', 'max:100'],
            'class_name' => ['required', 'string', 'max:50'],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'pass_mark' => ['required', 'integer', 'min:1', 'max:100'],
        ]);

        $test->update($validated);

        return back()->with('status', "\"{$test->title}\" was updated.");
    }

    public function updateStatus(Request $request, CbtTest $test): RedirectResponse
    {
        $this->authorizeTest($test);

        $validated = $request->validate([
            'status' => ['required', Rule::enum(CbtTestStatus::class)],
            'available_from' => ['nullable', 'date'],
            'available_until' => ['nullable', 'date', 'after:available_from'],
        ]);

        $newStatus = CbtTestStatus::from($validated['status']);

        if ($newStatus === CbtTestStatus::Published) {
            abort_if($test->questions()->count() === 0, 422, 'This test has no questions yet.');
        }

        if (in_array($newStatus, [CbtTestStatus::Draft, CbtTestStatus::Locked], true) && $test->hasStudentAttempts()) {
            abort(422, 'This test cannot be locked or unlocked - students have already started it. You can still archive it.');
        }

        $test->update([
            'status' => $newStatus,
            'available_from' => $validated['available_from'] ?? null,
            'available_until' => $validated['available_until'] ?? null,
        ]);

        return back()->with('status', "\"{$test->title}\" is now {$test->status->label()}.");
    }

    private function authorizeTest(CbtTest $test): void
    {
        $this->authorizeSchoolOwnership($test);
    }
}
