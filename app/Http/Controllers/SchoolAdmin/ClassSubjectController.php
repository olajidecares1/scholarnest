<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\SubjectCategory;
use App\Http\Controllers\Controller;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Services\DefaultAcademicStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class ClassSubjectController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        DefaultAcademicStructure::seedFor($school);

        $classOptions = $school->academicLevels()->with('classes')->get()->flatMap->classes->pluck('name', 'name');
        $className = $request->filled('class') ? $request->string('class')->toString() : $classOptions->keys()->first();

        $offered = $className ? $school->offeredSubjectsFor($className) : collect();

        $catalogue = Subject::orderBy('name')->get()->groupBy(fn (Subject $subject) => $subject->category->value);

        return view('school-admin.class-subjects.index', [
            'classOptions' => $classOptions,
            'selectedClass' => $className,
            'offered' => $offered,
            'catalogue' => $catalogue,
            'categoryOptions' => SubjectCategory::cases(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:50'],
            'subject_ids' => ['nullable', 'array'],
            'subject_ids.*' => ['integer', 'exists:subjects,id'],
            'custom_subject' => ['nullable', 'string', 'max:100'],
        ]);

        $subjectIds = collect($validated['subject_ids'] ?? [])->map(fn ($id) => (int) $id);

        if (! empty($validated['custom_subject'])) {
            $custom = Subject::firstOrCreate(
                ['name' => $validated['custom_subject']],
                ['category' => SubjectCategory::General],
            );
            $subjectIds->push($custom->id);
        }

        $subjectIds = $subjectIds->unique()->values();

        SubjectOffering::where('school_id', $school->id)->where('class_name', $validated['class_name'])->delete();

        foreach ($subjectIds as $subjectId) {
            SubjectOffering::create([
                'school_id' => $school->id,
                'class_name' => $validated['class_name'],
                'subject_id' => $subjectId,
            ]);
        }

        return back()->with('status', "Subjects for {$validated['class_name']} were saved.");
    }
}
