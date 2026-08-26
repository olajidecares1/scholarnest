<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\MemorandumAudience;
use App\Http\Controllers\Controller;
use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Notifications\NewNoticePosted;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Memorandums: one message, addressed to whoever the school means.
 *
 * This used to send to students and only students - the audience was implicit
 * in the code rather than chosen by the sender. A school that wanted to tell
 * its teachers something had no way to, and one addressing everybody would
 * have had to write the same memo three times.
 */
class NoticeController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.notices.index', [
            'school' => $school,
            'notices' => $school->notices()->latest()->paginate(10),
            'academicLevels' => $school->academicLevels()->with('classes')->get(),

            // Only the audiences this school can actually reach. Offering a
            // Basic school "Parents/Guardians" would be offering to send a
            // memorandum to accounts that do not exist.
            'audiences' => MemorandumAudience::availableTo($school),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $available = collect(MemorandumAudience::availableTo($school))
            ->map(fn (MemorandumAudience $audience) => $audience->value)
            ->all();

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'body' => ['required', 'string', 'max:5000'],
            'audience' => ['required', Rule::in($available)],

            // A class filter only narrows the people who belong to a class.
            'class_name' => ['nullable', 'string', 'max:50'],
        ], [
            'audience.in' => 'Choose a group your plan can send to.',
        ]);

        $audience = MemorandumAudience::from($validated['audience']);

        $notice = $school->notices()->create([
            ...$validated,
            'sent_by' => $request->user()->id,
        ]);

        $sent = [];

        foreach ($audience->groupsFor($school) as $group) {
            $recipients = match ($group) {
                MemorandumAudience::Staff => Staff::where('school_id', $school->id)
                    ->where('is_active', true)
                    ->get(),

                MemorandumAudience::Students => Student::where('school_id', $school->id)
                    ->where('is_active', true)
                    ->when($notice->class_name, fn ($query) => $query->where('class_name', $notice->class_name))
                    ->get(),

                // A guardian is reached through their children, so a memo
                // narrowed to one class reaches the parents of that class and
                // nobody else's. Distinct, because a parent with two children
                // in the same class is still one parent.
                MemorandumAudience::Guardians => Guardian::where('school_id', $school->id)
                    ->where('is_active', true)
                    ->when($notice->class_name, fn ($query) => $query->whereHas(
                        'students',
                        fn ($students) => $students->where('class_name', $notice->class_name),
                    ))
                    ->get(),

                default => collect(),
            };

            $recipients->each(fn ($recipient) => $recipient->notify(new NewNoticePosted($notice)));

            if ($recipients->isNotEmpty()) {
                $sent[] = "{$recipients->count()} {$group->label()}";
            }
        }

        if ($sent === []) {
            return back()->with('status', 'Memorandum saved, but there was nobody in that group to send it to.');
        }

        return back()->with('status', 'Memorandum sent to '.implode(', ', $sent).'.');
    }
}
