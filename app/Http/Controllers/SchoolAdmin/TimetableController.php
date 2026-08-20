<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\TimetableEntry;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class TimetableController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $classes = $school->academicLevels()->with('classes')->get()->flatMap->classes->pluck('name')->values();
        $selectedClass = $request->string('class')->toString() ?: $classes->first();

        $entries = $school->timetableEntries()
            ->when($selectedClass, fn ($q) => $q->where('class_name', $selectedClass))
            ->orderBy('day_of_week')
            ->orderBy('start_time')
            ->get()
            ->groupBy('day_of_week');

        return view('school-admin.timetable.index', [
            'classes' => $classes,
            'selectedClass' => $selectedClass,
            'entries' => $entries,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $school->timetableEntries()->create($validated);

        return back()->with('status', 'Timetable entry added.');
    }

    public function update(Request $request, TimetableEntry $timetableEntry): RedirectResponse
    {
        $this->authorizeEntry($timetableEntry);

        $validated = $request->validate($this->rules());

        $timetableEntry->update($validated);

        return back()->with('status', 'Timetable entry updated.');
    }

    public function destroy(TimetableEntry $timetableEntry): RedirectResponse
    {
        $this->authorizeEntry($timetableEntry);

        $timetableEntry->delete();

        return back()->with('status', 'Timetable entry removed.');
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'class_name' => ['required', 'string', 'max:50'],
            'day_of_week' => ['required', 'integer', 'between:1,7'],
            'start_time' => ['required', 'date_format:H:i'],
            'end_time' => ['required', 'date_format:H:i', 'after:start_time'],
            'subject' => ['required', 'string', 'max:100'],
            'room' => ['nullable', 'string', 'max:50'],
        ];
    }

    private function authorizeEntry(TimetableEntry $entry): void
    {
        abort_unless($entry->school_id === auth()->user()->school_id, 403);
    }
}
