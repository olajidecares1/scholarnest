<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\CbtExamBody;
use App\Models\CbtExamBodyClassGrant;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CbtPracticeController extends Controller
{
    public function index(): View
    {
        return view('school-admin.cbt-practice.index', [
            'examBodies' => CbtExamBody::withCount(['subjects', 'exams'])->orderBy('name')->get(),
        ]);
    }

    public function show(Request $request, CbtExamBody $examBody): View
    {
        return view('school-admin.cbt-practice.show', [
            'examBody' => $examBody,
            'exams' => $examBody->exams()->with('subject')->withCount('questions')->orderByDesc('year')->get(),
            'grants' => $examBody->classGrants()->where('school_id', $request->user()->school_id)->get(),
        ]);
    }

    public function storeGrant(Request $request, CbtExamBody $examBody): RedirectResponse
    {
        $validated = $request->validate([
            'class_name' => ['required', 'string', 'max:50'],
        ]);

        CbtExamBodyClassGrant::firstOrCreate([
            'school_id' => $request->user()->school_id,
            'cbt_exam_body_id' => $examBody->id,
            'class_name' => $validated['class_name'],
        ], [
            'granted_by' => $request->user()->id,
        ]);

        return back()->with('status', "{$examBody->name} granted to {$validated['class_name']}.");
    }

    public function destroyGrant(CbtExamBodyClassGrant $grant): RedirectResponse
    {
        abort_unless($grant->school_id === auth()->user()->school_id, 403);

        $grant->delete();

        return back()->with('status', 'Access grant removed.');
    }
}
