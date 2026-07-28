<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\AcademicLevel;
use App\Models\SchoolClass;
use App\Services\DefaultAcademicStructure;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class AcademicController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        DefaultAcademicStructure::seedFor($school);

        return view('school-admin.academics.index', [
            'levels' => $school->academicLevels()->with('classes')->get(),
        ]);
    }

    public function storeLevel(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);

        $school->academicLevels()->create([
            ...$validated,
            'sort_order' => $school->academicLevels()->count(),
        ]);

        return back()->with('status', "\"{$validated['name']}\" level added successfully.");
    }

    public function updateLevel(Request $request, AcademicLevel $level): RedirectResponse
    {
        $this->authorizeLevel($level);

        $validated = $request->validate(['name' => ['required', 'string', 'max:100']]);
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

        $validated = $request->validate(['name' => ['required', 'string', 'max:50']]);

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

        $validated = $request->validate(['name' => ['required', 'string', 'max:50']]);
        $class->update($validated);

        return back()->with('status', "\"{$class->name}\" updated successfully.");
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
