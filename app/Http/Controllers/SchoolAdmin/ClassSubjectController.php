<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\SubjectCategory;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Services\DefaultAcademicStructure;
use App\Services\JuniorSecondarySubjects;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class ClassSubjectController extends Controller
{
    /** More than any real class offers, and small enough to bound one request. */
    private const MAX_SUBJECTS = 60;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        DefaultAcademicStructure::seedFor($school);

        $classOptions = $school->academicLevels()->with('classes')->get()->flatMap->classes->pluck('name', 'name');
        // Only one of the school's own classes can be picked; anything else in
        // the query string falls back to the first class.
        $requested = $request->string('class')->toString();
        $className = $classOptions->has($requested) ? $requested : $classOptions->keys()->first();

        $selectedClassModel = $className ? $school->schoolClasses()->where('name', $className)->with('academicLevel')->first() : null;

        // A JSS class always has its default subjects; fill in any it is
        // missing before showing it (e.g. the defaults list was edited).
        if ($selectedClassModel) {
            JuniorSecondarySubjects::ensureForClass($selectedClassModel);
        }

        $offered = $className ? $school->offeredSubjectsFor($className) : collect();

        // A JSS class shows its default subjects first, numbered in
        // result-sheet order and locked, and the rest of the catalogue below.
        $jssSubjects = $className
            ? JuniorSecondarySubjects::sortByName(
                SubjectOffering::with('subject')
                    ->where('school_id', $school->id)
                    ->where('class_name', $className)
                    ->where('is_default', true)
                    ->get()
                    ->pluck('subject')
                    ->filter(),
                $className,
                $selectedClassModel?->academicLevel?->name,
                fn (Subject $subject) => $subject->name,
            )
            : collect();

        $catalogue = Subject::orderBy('name')
            ->whereNotIn('id', $jssSubjects->pluck('id'))
            ->get()
            ->groupBy(fn (Subject $subject) => $subject->category->value);

        return view('school-admin.class-subjects.index', [
            'classOptions' => $classOptions,
            'selectedClass' => $className,
            'offered' => $offered,
            'catalogue' => $catalogue,
            'jssSubjects' => $jssSubjects,
            'categoryOptions' => SubjectCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        // A school that has never opened Academics still gets its default
        // classes, so the class check below has something to check against.
        DefaultAcademicStructure::seedFor($school);

        $validated = $request->validate([
            // One of this school's own classes, never a made-up name.
            'class_name' => ['required', 'string', 'max:50', Rule::in($school->configuredClassNames())],
            'subject_ids' => ['nullable', 'array', 'max:'.self::MAX_SUBJECTS],
            'subject_ids.*' => ['integer', 'distinct', 'exists:subjects,id'],
            // Letters, numbers, spaces and the punctuation real subject names
            // use (e.g. "CRS/IRS", "Cultural & Creative Art", "Literature-in-English").
            'custom_subject' => ['nullable', 'string', 'min:2', 'max:100', "regex:/^[\\pL\\pN][\\pL\\pN\\s&\\/\\-'().,]*$/u"],
        ], [
            'class_name.in' => 'Pick one of your school\'s classes.',
            'subject_ids.max' => 'A class can offer at most '.self::MAX_SUBJECTS.' subjects.',
            'custom_subject.regex' => 'A subject name can only use letters, numbers, spaces and & / - \' ( ) . ,',
        ]);

        $className = $validated['class_name'];
        $subjectIds = collect($validated['subject_ids'] ?? [])->map(fn ($id) => (int) $id);

        // Default subjects stay whatever was ticked: they are added for the
        // class by the platform, and a School Admin cannot remove them.
        $defaultIds = SubjectOffering::where('school_id', $school->id)
            ->where('class_name', $className)
            ->where('is_default', true)
            ->pluck('subject_id');

        DB::transaction(function () use ($school, $className, &$subjectIds, $validated, $defaultIds) {
            if (! empty($validated['custom_subject'])) {
                $subjectIds->push(self::catalogueSubjectNamed($validated['custom_subject'])->id);
            }

            $subjectIds = $subjectIds->unique()->values();

            // Replace the school's OWN subjects for the class in one go: if
            // anything fails, the class keeps the subjects it had rather
            // than being left with none. Default offerings are not touched.
            SubjectOffering::where('school_id', $school->id)
                ->where('class_name', $className)
                ->where('is_default', false)
                ->whereNotIn('subject_id', $subjectIds)
                ->delete();

            foreach ($subjectIds->diff($defaultIds) as $subjectId) {
                SubjectOffering::firstOrCreate([
                    'school_id' => $school->id,
                    'class_name' => $className,
                    'subject_id' => $subjectId,
                ]);
            }
        });

        if ($defaultIds->diff($subjectIds)->isNotEmpty()) {
            return back()->with('status', "Subjects for {$className} were saved. Default subjects can't be removed, so they were kept.");
        }

        return back()->with('status', "Subjects for {$className} were saved.");
    }

    /**
     * The catalogue is shared by every school, so a custom subject reuses an
     * existing entry whatever its capitalisation or spacing ("basic science",
     * "Basic  Science") instead of adding a near-duplicate.
     */
    private static function catalogueSubjectNamed(string $name): Subject
    {
        $name = trim(preg_replace('/\s+/u', ' ', $name));
        $wanted = JuniorSecondarySubjects::normalize($name);

        // "physical and health education" finds the PHE the catalogue
        // already has, rather than starting a second copy of it.
        $default = JuniorSecondarySubjects::defaultNameFor($name);
        $wantedNames = $default !== null
            ? collect([$default, ...JuniorSecondarySubjects::definitions()[$default]['aliases']])->map(fn ($n) => JuniorSecondarySubjects::normalize($n))
            : collect([$wanted]);

        $catalogue = Subject::query()->orderBy('id')->get();

        // The exact name first ("Computer Studies" is its own catalogue
        // entry), then any other spelling of the same default subject.
        $existing = $catalogue->first(fn (Subject $subject) => JuniorSecondarySubjects::normalize($subject->name) === $wanted)
            ?? $catalogue->first(fn (Subject $subject) => $wantedNames->contains(JuniorSecondarySubjects::normalize($subject->name)));

        return $existing ?? Subject::create([
            'name' => $name,
            'category' => SubjectCategory::General,
        ]);
    }
}
