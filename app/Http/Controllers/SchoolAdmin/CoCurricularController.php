<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\CoCurricularActivity;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CoCurricularController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.co-curricular.index', [
            'activities' => $school->coCurricularActivities()->withCount('students')->orderBy('name')->get(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $activity = $school->coCurricularActivities()->create($validated);

        return back()->with('status', "\"{$activity->name}\" was added.");
    }

    public function update(Request $request, CoCurricularActivity $activity): RedirectResponse
    {
        $this->authorizeActivity($activity);

        $activity->update($request->validate($this->rules()));

        return back()->with('status', "\"{$activity->name}\" was updated.");
    }

    public function destroy(CoCurricularActivity $activity): RedirectResponse
    {
        $this->authorizeActivity($activity);

        $name = $activity->name;
        $activity->delete();

        return back()->with('status', "\"{$name}\" was removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'schedule_text' => ['nullable', 'string', 'max:150'],
        ];
    }

    private function authorizeActivity(CoCurricularActivity $activity): void
    {
        abort_unless($activity->school_id === auth()->user()->school_id, 403);
    }
}
