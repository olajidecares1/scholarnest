<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtSubject;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class CbtExamBodyController extends Controller
{
    public function index(): View
    {
        $examBodies = CbtExamBody::withCount(['subjects', 'exams', 'questions'])
            ->orderBy('name')
            ->get();

        return view('super-admin.cbt.index', [
            'examBodies' => $examBodies,
            'subjects' => CbtSubject::orderBy('name')->get(),
            'totalQuestions' => CbtQuestion::count(),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:cbt_exam_bodies,code'],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $examBody = CbtExamBody::create($validated);

        AuditLog::record('cbt.exam_body.created', "Created CBT exam body \"{$examBody->name}\".", $examBody);

        return back()->with('status', "\"{$examBody->name}\" added successfully.");
    }

    public function update(Request $request, CbtExamBody $examBody): RedirectResponse
    {
        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'code' => ['required', 'string', 'max:20', 'alpha_dash', 'unique:cbt_exam_bodies,code,'.$examBody->id],
            'description' => ['nullable', 'string', 'max:1000'],
        ]);

        $examBody->update($validated);

        AuditLog::record('cbt.exam_body.updated', "Updated CBT exam body \"{$examBody->name}\".", $examBody);

        return back()->with('status', "\"{$examBody->name}\" updated successfully.");
    }

    public function updateSubjects(Request $request, CbtExamBody $examBody): RedirectResponse
    {
        $validated = $request->validate([
            'subjects' => ['array'],
            'subjects.*' => ['integer', 'exists:cbt_subjects,id'],
        ]);

        $examBody->subjects()->sync($validated['subjects'] ?? []);

        AuditLog::record('cbt.exam_body.subjects_updated', "Updated subjects offered by \"{$examBody->name}\".", $examBody);

        return back()->with('status', 'Subjects updated successfully.');
    }

    public function destroy(CbtExamBody $examBody): RedirectResponse
    {
        $name = $examBody->name;
        $examBody->delete();

        AuditLog::record('cbt.exam_body.deleted', "Deleted CBT exam body \"{$name}\".");

        return redirect()->route('super-admin.cbt.index')->with('status', "\"{$name}\" deleted successfully.");
    }

    public function show(CbtExamBody $examBody): View
    {
        $examBody->load([
            'subjects' => fn ($query) => $query->orderBy('name'),
            'exams' => fn ($query) => $query->with('subject')->withCount('questions')->orderByDesc('year'),
        ]);

        return view('super-admin.cbt.exam-body', [
            'examBody' => $examBody,
            'allSubjects' => CbtSubject::orderBy('category')->orderBy('name')->get(),
        ]);
    }
}
