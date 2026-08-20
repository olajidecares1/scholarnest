<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ClassStream;
use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use App\Models\AcademicTerm;
use App\Models\GradeBand;
use App\Models\SchoolClass;
use App\Services\DefaultAcademicStructure;
use App\Support\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        DefaultAcademicStructure::seedFor($school);

        return view('school-admin.academics.index', [
            'levels' => $school->academicLevels()->with('classes')->get(),
            'terms' => AcademicTerm::where('school_id', $school->id)->orderByDesc('session')->orderBy('term')->get(),
            'gradeBands' => $school->gradeBands,
            'sessionOptions' => AcademicSession::options(),
            'termOptions' => ExamTerm::cases(),
        ]);
    }

    public function storeTerm(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'session' => ['required', Rule::in(AcademicSession::options())],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'starts_on' => ['required', 'date'],
            'ends_on' => ['required', 'date', 'after_or_equal:starts_on'],
        ]);

        AcademicTerm::updateOrCreate(
            ['school_id' => $school->id, 'session' => $validated['session'], 'term' => $validated['term']],
            ['starts_on' => $validated['starts_on'], 'ends_on' => $validated['ends_on']],
        );

        return back()->with('status', 'Term dates saved.');
    }

    public function destroyTerm(AcademicTerm $term): RedirectResponse
    {
        abort_unless($term->school_id === auth()->user()->school_id, 403);

        $term->delete();

        return back()->with('status', 'Term dates removed.');
    }

    public function storeGradeBand(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->gradeBandRules());

        $school->gradeBands()->create([
            ...$validated,
            'position' => $school->gradeBands()->count(),
        ]);

        return back()->with('status', "Grade \"{$validated['letter']}\" added.");
    }

    public function updateGradeBand(Request $request, GradeBand $gradeBand): RedirectResponse
    {
        $this->authorizeGradeBand($gradeBand);

        $validated = $request->validate($this->gradeBandRules());
        $gradeBand->update($validated);

        return back()->with('status', "Grade \"{$gradeBand->letter}\" updated.");
    }

    public function destroyGradeBand(GradeBand $gradeBand): RedirectResponse
    {
        $this->authorizeGradeBand($gradeBand);

        $letter = $gradeBand->letter;
        $gradeBand->delete();

        return back()->with('status', "Grade \"{$letter}\" removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function gradeBandRules(): array
    {
        return [
            'min_percent' => ['required', 'integer', 'min:0', 'max:100'],
            'max_percent' => ['required', 'integer', 'min:0', 'max:100', 'gte:min_percent'],
            'letter' => ['required', 'string', 'max:3'],
            'description' => ['nullable', 'string', 'max:100'],
        ];
    }

    private function authorizeGradeBand(GradeBand $gradeBand): void
    {
        abort_unless($gradeBand->school_id === auth()->user()->school_id, 403);
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);

        $school->academicLevels()->create([
            ...$validated,
            'sort_order' => $school->academicLevels()->count(),
        ]);

        return back()->with('status', "\"{$validated['name']}\" level added successfully.");
    }

    public function updateLevel(Request $request, AcademicLevel $level): RedirectResponse
    {
        $this->authorizeLevel($level);

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:100'],
            'code' => ['nullable', 'string', 'max:10'],
        ]);
        $level->update($validated);

        return back()->with('status', "\"{$level->name}\" updated successfully.");
    }

    public function destroyLevel(AcademicLevel $level): RedirectResponse
    {
        $this->authorizeLevel($level);

        $name = $level->name;
        $level->delete();

        return back()->with('status', "\"{$name}\" and its classes were removed.");
    }

    public function storeClass(Request $request, AcademicLevel $level): RedirectResponse
    {
        $this->authorizeLevel($level);

        $validated = $request->validate($this->classRules());

        $level->classes()->create([
            ...$validated,
            'school_id' => $level->school_id,
            'sort_order' => $level->classes()->count(),
        ]);

        return back()->with('status', "\"{$validated['name']}\" added successfully.");
    }

    public function updateClass(Request $request, SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $validated = $request->validate($this->classRules());
        $class->update($validated);

        return back()->with('status', "\"{$class->name}\" updated successfully.");
    }

    /**
     * @return array<string, mixed>
     */
    private function classRules(): array
    {
        return [
            'name' => ['required', 'string', 'max:50'],
            'stream' => ['nullable', Rule::enum(ClassStream::class)],
        ];
    }

    public function destroyClass(SchoolClass $class): RedirectResponse
    {
        $this->authorizeClass($class);

        $name = $class->name;
        $class->delete();

        return back()->with('status', "\"{$name}\" removed successfully.");
    }

    private function authorizeLevel(AcademicLevel $level): void
    {
        abort_unless($level->school_id === auth()->user()->school_id, 403);
    }

    private function authorizeClass(SchoolClass $class): void
    {
        abort_unless($class->school_id === auth()->user()->school_id, 403);
    }
}
