<?php

namespace App\Http\Controllers\Staff;

use App\Enums\DiaryEntryStatus;
use App\Enums\ExamTerm;
use App\Enums\UserRole;
use App\Http\Controllers\Controller;
use App\Models\School;
use App\Models\TeacherDiaryEntry;
use App\Models\User;
use App\Notifications\DiaryEntrySubmitted;
use App\Support\AcademicSession;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * The teacher's side of the diary: log this week's topic, subject by subject.
 *
 * A teacher only ever sees and writes their own entries. The subjects they may
 * write against are their own assignments rather than a free-text box, so an
 * entry cannot end up filed under a subject or class they do not teach, and
 * the choice is re-checked here, because a select element is a convenience and
 * not a boundary.
 */
class DiaryController extends Controller
{
    /**
     * Weeks in a term. Thirteen or so is the usual shape, and a number the
     * teacher picks is more honest than one derived from today's date, they
     * are often writing up a week late.
     */
    private const WEEKS_IN_TERM = 14;

    public function index(Request $request, School $school): View
    {
        $teacher = $request->user('staff');

        $entries = TeacherDiaryEntry::forSchool($school)
            ->where('staff_id', $teacher->id)
            ->with('seenBy')
            ->orderByDesc('session')
            ->orderByDesc('week_number')
            ->paginate(20);

        return view('staff.diary.index', [
            'school' => $school,
            'teacher' => $teacher,
            'entries' => $entries,

            // What this teacher actually teaches. An empty list is its own
            // answer, see the view, rather than an empty dropdown.
            'assignments' => $teacher->subjectAssignments(),

            'sessions' => collect([AcademicSession::current(), $school->current_session])
                ->filter()
                ->unique()
                ->values(),
            'terms' => ExamTerm::cases(),
            'weeks' => range(1, self::WEEKS_IN_TERM),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $teacher = $request->user('staff');

        // The class and subject have to be a pair this teacher is assigned to,
        // together. Validating them separately would let a teacher who teaches
        // Maths to 2A and English to 3B file "English, 2A".
        $pairs = $teacher->subjectAssignments()
            ->map(fn (array $assignment) => $assignment['class_name'].'|'.$assignment['subject'])
            ->all();

        $validated = $request->validate([
            'assignment' => ['required', Rule::in($pairs)],
            'session' => ['required', 'string', 'max:20'],
            'term' => ['required', Rule::enum(ExamTerm::class)],
            'week_number' => ['required', 'integer', 'min:1', 'max:'.self::WEEKS_IN_TERM],
            'topic' => ['required', 'string', 'max:2000'],
        ], [
            'assignment.required' => 'Choose the subject and class this topic was taught to.',
            'assignment.in' => 'You can only write a diary entry for a subject you are assigned to teach.',
        ]);

        [$className, $subject] = explode('|', $validated['assignment'], 2);

        // Revising what was written for a week updates that week rather than
        // adding a second entry for it, which is what the unique key on the
        // table enforces, and what a teacher expects when correcting a typo.
        $entry = TeacherDiaryEntry::updateOrCreate(
            [
                'staff_id' => $teacher->id,
                'class_name' => $className,
                'subject' => $subject,
                'session' => $validated['session'],
                'term' => $validated['term'],
                'week_number' => $validated['week_number'],
            ],
            [
                'school_id' => $school->id,
                'topic' => $validated['topic'],

                // An edited entry goes back to the school for review. The
                // school approved what it read, not whatever replaced it.
                'status' => DiaryEntryStatus::Submitted,
                'seen_by' => null,
                'seen_at' => null,
            ],
        );

        User::where('school_id', $school->id)
            ->where('role', UserRole::SchoolAdmin)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new DiaryEntrySubmitted($entry)));

        return back()->with('status', sprintf(
            'Week %d of %s recorded for %s (%s). Your school has been notified.',
            $entry->week_number,
            $entry->term->label(),
            $subject,
            $className,
        ));
    }
}
