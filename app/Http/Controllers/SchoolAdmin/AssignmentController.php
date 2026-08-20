<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\SubmissionStatus;
use App\Http\Controllers\Controller;
use App\Models\Assignment;
use App\Models\Student;
use App\Notifications\NewAssignmentPosted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class AssignmentController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $assignments = $school->assignments()
            ->withCount(['submissions as graded_count' => fn ($query) => $query->where('status', SubmissionStatus::Graded)])
            ->when($request->filled('class'), fn ($query) => $query->where('class_name', $request->string('class')))
            ->when($request->filled('subject'), fn ($query) => $query->where('subject', 'like', '%'.$request->string('subject').'%'))
            ->orderByDesc('due_date')
            ->paginate(15)
            ->withQueryString();

        return view('school-admin.assignments.index', [
            'assignments' => $assignments,
            'academicLevels' => $school->academicLevels()->with('classes')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $assignment = $school->assignments()->create($validated);

        Student::where('school_id', $school->id)
            ->where('class_name', $assignment->class_name)
            ->where('is_active', true)
            ->get()
            ->each(fn (Student $student) => $student->notify(new NewAssignmentPosted($assignment)));

        return redirect()->route('assignments.show', $assignment)->with('status', "{$assignment->title} was created.");
    }

    public function show(Assignment $assignment): View
    {
        $this->authorizeAssignment($assignment);

        $students = Student::where('school_id', $assignment->school_id)
            ->where('class_name', $assignment->class_name)
            ->where('is_active', true)
            ->orderBy('last_name')
            ->get();

        $existing = $assignment->submissions()->whereIn('student_id', $students->pluck('id'))->get()->keyBy('student_id');

        return view('school-admin.assignments.show', [
            'assignment' => $assignment,
            'students' => $students,
            'existing' => $existing,
            'statusOptions' => SubmissionStatus::cases(),
        ]);
    }

    public function update(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($assignment);

        $validated = $request->validate($this->rules());
        $assignment->update($validated);

        return back()->with('status', "{$assignment->title} was updated.");
    }

    public function destroy(Assignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($assignment);

        $title = $assignment->title;
        $assignment->delete();

        return redirect()->route('assignments.index')->with('status', "{$title} was deleted.");
    }

    public function storeSubmissions(Request $request, Assignment $assignment): RedirectResponse
    {
        $this->authorizeAssignment($assignment);

        $validated = $request->validate([
            'submissions' => ['required', 'array'],
            'submissions.*.status' => ['required', Rule::enum(SubmissionStatus::class)],
            'submissions.*.score' => ['nullable', 'numeric', 'min:0', 'max:'.$assignment->max_score],
            'submissions.*.feedback' => ['nullable', 'string', 'max:1000'],
        ]);

        $students = Student::where('school_id', $assignment->school_id)
            ->whereIn('id', array_keys($validated['submissions']))
            ->get()
            ->keyBy('id');

        $saved = 0;
        foreach ($validated['submissions'] as $studentId => $data) {
            if (! $students->has($studentId)) {
                continue;
            }

            $assignment->submissions()->updateOrCreate(
                ['student_id' => $studentId],
                [
                    'status' => $data['status'],
                    'score' => $data['score'] ?? null,
                    'feedback' => $data['feedback'] ?? null,
                ],
            );
            $saved++;
        }

        return redirect()->route('assignments.show', $assignment)->with('status', "Saved submission status for {$saved} student(s).");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'class_name' => ['required', 'string', 'max:50'],
            'subject' => ['required', 'string', 'max:100'],
            'title' => ['required', 'string', 'max:150'],
            'description' => ['nullable', 'string', 'max:5000'],
            'due_date' => ['required', 'date'],
            'max_score' => ['required', 'integer', 'min:1', 'max:1000'],
        ];
    }

    private function authorizeAssignment(Assignment $assignment): void
    {
        abort_unless($assignment->school_id === auth()->user()->school_id, 403);
    }
}
