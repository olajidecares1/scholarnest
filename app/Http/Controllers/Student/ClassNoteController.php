<?php

namespace App\Http\Controllers\Student;

use App\Http\Controllers\Controller;
use App\Models\ClassNote;
use App\Models\School;
use App\Notifications\NewClassNotePosted;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * A pupil's class notes.
 *
 * TWO CONDITIONS ON EVERY QUERY, always together: the pupil's own school, and
 * the pupil's own class. Either alone is a hole, two schools may each have a
 * class called "JSS 1A", so a class-only filter would hand one school's notes
 * to the other's pupils, and a school-only filter would show a pupil every
 * class's work.
 *
 * Both come from the SIGNED-IN PUPIL, never from the request. There is no
 * parameter here a pupil could change to see another class: the only thing
 * they supply is which note they want, and that note is then checked against
 * the class they are actually in.
 *
 * This whole controller is behind `portal_access`, so it exists only for
 * schools whose plan has a student portal at all. Basic never reaches it.
 */
class ClassNoteController extends Controller
{
    public function index(Request $request, School $school): View
    {
        $student = $request->user('student');

        $notes = ClassNote::forSchool($student->school_id)
            ->forClass($student->class_name)
            ->with(['classes', 'staff:id,first_name,last_name'])
            ->latest()
            ->paginate(10);

        return view('student.class-notes.index', [
            'school' => $school,
            'student' => $student,
            'notes' => $notes,
        ]);
    }

    /**
     * Read one note: its text on screen, ready to copy, and the document
     * itself to download.
     */
    public function show(Request $request, School $school, ClassNote $note): View
    {
        $student = $request->user('student');

        $this->authorizeNote($note, $student->school_id, $student->class_name);

        // Reading it is acknowledging it. Anything in the pupil's
        // notifications that points here has been dealt with.
        $this->markNotificationsRead($request, $note);

        return view('student.class-notes.show', [
            'school' => $school,
            'student' => $student,
            'note' => $note->load(['classes', 'staff:id,first_name,last_name']),
        ]);
    }

    /**
     * The Word document itself.
     */
    public function download(Request $request, School $school, ClassNote $note): StreamedResponse
    {
        $student = $request->user('student');

        $this->authorizeNote($note, $student->school_id, $student->class_name);

        abort_unless($note->fileExists(), 404);

        return Storage::disk(ClassNote::DISK)->download($note->path, $note->original_name);
    }

    /**
     * The note must belong to this pupil's school AND have been sent to this
     * pupil's class.
     *
     * 404 rather than 403 on the class check: whether a note exists in another
     * class is not a pupil's business either.
     */
    private function authorizeNote(ClassNote $note, ?int $schoolId, ?string $className): void
    {
        abort_if($schoolId === null, 403);
        abort_unless($note->school_id === $schoolId, 404);
        abort_unless($note->wasSentTo($className), 404);
    }

    /**
     * Clear the notification that brought them here.
     *
     * Scoped to this pupil's own notifications and to this note's URL, so
     * opening one note does not silently mark everything else as read.
     */
    private function markNotificationsRead(Request $request, ClassNote $note): void
    {
        $url = route('student.class-notes.show', [$note->school, $note]);

        $request->user('student')
            ->unreadNotifications()
            ->where('type', NewClassNotePosted::class)
            ->get()
            ->filter(fn ($notification) => ($notification->data['url'] ?? null) === $url)
            ->each->markAsRead();
    }
}
