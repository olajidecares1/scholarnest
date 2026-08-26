<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\DiaryEntryStatus;
use App\Enums\ExamTerm;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\TeacherDiaryEntry;
use App\Notifications\DiaryEntrySeen;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * The school's side of the diary: read what was taught, and say so.
 *
 * Marking an entry seen is the half of the loop the teacher can observe. A
 * diary submitted into silence gives a teacher no way to tell one that is
 * being read from one that is not, and they stop writing it carefully.
 */
class DiaryController extends Controller
{
    public function index(Request $request): View
    {
        $school = $request->user()->school;

        $entries = TeacherDiaryEntry::forSchool($school)
            ->with(['teacher', 'seenBy'])
            ->when($request->filled('status'), fn ($query) => $query->where('status', $request->string('status')))
            ->when($request->filled('staff_id'), fn ($query) => $query->where('staff_id', $request->integer('staff_id')))
            ->when($request->filled('class_name'), fn ($query) => $query->where('class_name', $request->string('class_name')))
            ->when($request->filled('term'), fn ($query) => $query->where('term', $request->string('term')))
            ->when($request->filled('session'), fn ($query) => $query->where('session', $request->string('session')))
            ->orderByDesc('created_at')
            ->paginate(20)
            ->withQueryString();

        return view('school-admin.diary.index', [
            'school' => $school,
            'entries' => $entries,
            'statuses' => DiaryEntryStatus::cases(),
            'terms' => ExamTerm::cases(),
            'teachers' => $school->staff()->orderBy('last_name')->get(),
            'classNames' => $school->configuredClassNames(),
            'sessions' => TeacherDiaryEntry::forSchool($school)->distinct()->orderByDesc('session')->pluck('session'),

            // The number a School Admin opens this page to see: how many are
            // still waiting to be read.
            'awaitingReview' => TeacherDiaryEntry::forSchool($school)
                ->where('status', DiaryEntryStatus::Submitted)
                ->count(),
        ]);
    }

    /**
     * Mark one entry as read.
     *
     * Bound by uuid and re-checked against this school, so the address cannot
     * be walked from one school's diary into another's.
     */
    public function markSeen(Request $request, TeacherDiaryEntry $entry): RedirectResponse
    {
        $school = $request->user()->school;

        abort_unless($entry->school_id === $school->id, 404);

        if ($entry->hasBeenSeen()) {
            return back()->with('status', 'That entry was already marked as seen.');
        }

        $entry->update([
            'status' => DiaryEntryStatus::Seen,
            'seen_by' => $request->user()->id,
            'seen_at' => now(),
        ]);

        AuditLog::record(
            'diary.entry.seen',
            sprintf(
                'Reviewed %s\'s diary entry for %s (%s), week %d of %s %s.',
                $entry->teacher->fullName(),
                $entry->subject,
                $entry->class_name,
                $entry->week_number,
                $entry->term->label(),
                $entry->session,
            ),
            $entry,
        );

        $entry->teacher->notify(new DiaryEntrySeen($entry->fresh('seenBy')));

        return back()->with('status', "{$entry->teacher->fullName()}'s entry was marked as seen, and they have been notified.");
    }
}
