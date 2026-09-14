<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\ClassStream;
use App\Enums\ExamTerm;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use App\Models\AcademicTerm;
use App\Models\GradeBand;
use App\Models\School;
use App\Models\SchoolClass;
use App\Services\DefaultAcademicStructure;
use App\Support\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AcademicController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        DefaultAcademicStructure::seedFor($school);

        return view('school-admin.academics.index', [
            'website' => $school->website,
            'levels' => $school->academicLevels()->with('classes')->get(),
            'terms' => AcademicTerm::where('school_id', $school->id)->orderByDesc('session')->orderBy('term')->get(),
            'gradeBands' => $school->gradeBands,

            // Once a school has its own scale, that scale is the only one its
            // pupils are graded on, so anything it leaves uncovered has to be
            // visible here rather than discovered on a report card.
            'gradeCoverageGaps' => GradeBand::coverageGaps($school),
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
        $this->authorizeSchoolOwnership($term);

        $term->delete();

        return back()->with('status', 'Term dates removed.');
    }

    public function storeGradeBand(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->gradeBandRules());

        if ($clash = $this->overlapError($school, $validated)) {
            return back()->withErrors(['min_percent' => $clash])->withInput();
        }

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

        if ($clash = $this->overlapError($gradeBand->school, $validated, $gradeBand->id)) {
            return back()->withErrors(['min_percent' => $clash])->withInput();
        }

        $gradeBand->update($validated);

        return back()->with('status', "Grade \"{$gradeBand->letter}\" updated.");
    }

    /**
     * Refuse a band that covers percentages another band already claims.
     *
     * Two bands over the same mark make the grade depend on which sorts first,
     * so a pupil on 65% could be a B or a C depending on the order rows happen
     * to come back in. Caught here rather than left to surface as an
     * inexplicable grade on a report card.
     *
     * @param  array<string, mixed>  $validated
     */
    private function overlapError(School $school, array $validated, ?int $ignoreId = null): ?string
    {
        $clashes = GradeBand::overlapsFor(
            $school,
            (int) $validated['min_percent'],
            (int) $validated['max_percent'],
            $ignoreId,
        );

        if ($clashes === []) {
            return null;
        }

        return sprintf(
            '%d to %d%% overlaps %s. Grade ranges cannot cover the same percentage twice.',
            $validated['min_percent'],
            $validated['max_percent'],
            implode(' and ', $clashes),
        );
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
        $this->authorizeSchoolOwnership($gradeBand);
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
        $this->authorizeSchoolOwnership($level);
    }

    private function authorizeClass(SchoolClass $class): void
    {
        $this->authorizeSchoolOwnership($class);
    }
}
