<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class CbtExamController extends Controller
{
    public function store(Request $request, CbtExamBody $examBody): RedirectResponse
    {
        $validated = $request->validate([
            'cbt_subject_id' => ['required', 'integer', 'exists:cbt_subjects,id'],
            'year' => [
                'required', 'integer', 'min:1999', 'max:'.now()->year,
                Rule::unique('cbt_exams', 'year')
                    ->where('cbt_exam_body_id', $examBody->id)
                    ->where('cbt_subject_id', $request->integer('cbt_subject_id')),
            ],
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'pass_mark' => ['required', 'integer', 'min:0', 'max:100'],
        ], [
            'year.unique' => 'An exam for this subject and year already exists.',
        ]);

        $exam = $examBody->exams()->create([
            ...$validated,
            'created_by' => $request->user()->id,
        ]);

        AuditLog::record('cbt.exam.created', "Created CBT exam \"{$exam->title()}\".", $exam);

        return redirect()->route('super-admin.cbt.exam-bodies.show', $examBody)->with('status', "\"{$exam->title()}\" created successfully.");
    }

    public function update(Request $request, CbtExam $exam): RedirectResponse
    {
        $validated = $request->validate([
            'duration_minutes' => ['required', 'integer', 'min:5', 'max:300'],
            'pass_mark' => ['required', 'integer', 'min:0', 'max:100'],
        ]);

        $exam->update($validated);

        AuditLog::record('cbt.exam.updated', "Updated CBT exam \"{$exam->title()}\".", $exam);

        return redirect()->route('super-admin.cbt.exam-bodies.show', $exam->examBody)->with('status', "\"{$exam->title()}\" updated successfully.");
    }

    public function show(CbtExam $exam): View
    {
        $exam->load(['examBody', 'subject', 'questions.options']);

        return view('super-admin.cbt.exam', [
            'exam' => $exam,
        ]);
    }

    public function destroy(CbtExam $exam): RedirectResponse
    {
        $examBody = $exam->examBody;
        $title = $exam->title();
        $exam->delete();

        AuditLog::record('cbt.exam.deleted', "Deleted CBT exam \"{$title}\".");

        return redirect()->route('super-admin.cbt.exam-bodies.show', $examBody)->with('status', "\"{$title}\" deleted successfully.");
    }
}
