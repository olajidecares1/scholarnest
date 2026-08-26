<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\ExaminationSubject;
use App\Models\Staff;
use App\Models\TeacherAssignment;
use App\Models\TimetableEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class TeacherAssignmentController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $assignments = TeacherAssignment::where('school_id', $school->id)
            ->with('staff')
            ->when($request->filled('teacher'), fn ($query) => $query->whereHas('staff', fn ($q) => $q->where('uuid', $request->string('teacher'))))
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->when($request->filled('subject'), fn ($query) => $query->where('subject', 'like', '%'.$request->string('subject').'%'))
            ->orderBy('class_name')
            ->get()
            ->groupBy(fn (TeacherAssignment $assignment) => $assignment->staff_id);

        $teachers = Staff::where('school_id', $school->id)->where('role', StaffRole::Teacher)->where('is_active', true)->orderBy('last_name')->get();
        $classOptions = $school->academicLevels()->with('classes')->get()->flatMap->classes->pluck('name', 'name');
        $subjectOptions = ExaminationSubject::whereHas('examination', fn ($q) => $q->where('school_id', $school->id))
            ->pluck('name')
            ->merge(TimetableEntry::where('school_id', $school->id)->pluck('subject'))
            ->unique()
            ->sort()
            ->values();

        // Classes with configured subject offerings get a constrained
        // dropdown in the Add Assignment form instead of free text - classes
        // that haven't been configured yet (the common case today) keep the
        // free-text + datalist behavior unaffected.
        $offeringsByClass = $school->subjectOfferings()
            ->with('subject')
            ->get()
            ->groupBy('class_name')
            ->map(fn ($offerings) => $offerings->pluck('subject.name')->sort()->values());

        return view('school-admin.teacher-assignments.index', [
            'assignments' => $assignments,
            'teachers' => $teachers,
            'classOptions' => $classOptions,
            'subjectOptions' => $subjectOptions,
            'offeringsByClass' => $offeringsByClass,
            'typeOptions' => TeacherAssignmentType::cases(),
            'selectedTeacher' => $request->string('teacher')->toString(),
            'selectedClass' => $request->string('class')->toString(),
            'selectedSubject' => $request->string('subject')->toString(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'staff_uuid' => [
                'required',
                Rule::exists('staff', 'uuid')->where('school_id', $school->id)->where('role', StaffRole::Teacher->value),
            ],
            'type' => ['required', Rule::enum(TeacherAssignmentType::class)],
            'class_name' => ['required', 'string', 'max:50'],
            'subject' => ['nullable', 'string', 'max:100', 'required_if:type,'.TeacherAssignmentType::SubjectTeacher->value],
        ]);

        $staff = Staff::where('school_id', $school->id)->where('uuid', $validated['staff_uuid'])->firstOrFail();
        $type = TeacherAssignmentType::from($validated['type']);

        if ($type === TeacherAssignmentType::ClassTeacher) {
            TeacherAssignment::updateOrCreate(
                ['school_id' => $school->id, 'class_name' => $validated['class_name'], 'type' => TeacherAssignmentType::ClassTeacher],
                ['staff_id' => $staff->id, 'subject' => null],
            );

            return back()->with('status', "{$staff->fullName()} is now the Class Teacher of {$validated['class_name']}.");
        }

        TeacherAssignment::firstOrCreate([
            'school_id' => $school->id,
            'staff_id' => $staff->id,
            'type' => TeacherAssignmentType::SubjectTeacher,
            'class_name' => $validated['class_name'],
            'subject' => $validated['subject'],
        ]);

        return back()->with('status', "{$staff->fullName()} was assigned {$validated['subject']} for {$validated['class_name']}.");
    }

    public function destroy(TeacherAssignment $assignment): RedirectResponse
    {
        $this->authorizeSchoolOwnership($assignment);

        $staffName = $assignment->staff->fullName();
        $assignment->delete();

        return back()->with('status', "The assignment for {$staffName} was removed.");
    }
}
