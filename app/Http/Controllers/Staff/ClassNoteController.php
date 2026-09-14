<?php

namespace App\Http\Controllers\Staff;

use App\Http\Controllers\Controller;
use App\Models\ClassNote;
use App\Models\School;
use App\Models\Student;
use App\Services\ClassNotePublisher;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\Rule;
use Illuminate\View\View;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * The teacher's side of Class Note: upload a Word document, tick the classes.
 *
 * Available to every member of staff on every plan, the staff portal itself
 * is, and this is part of the school's own teaching work rather than an
 * outward-facing extra. What a Basic school does not have is a STUDENT portal
 * to receive the note through, which is a fact about that plan and not
 * something this controller decides.
 *
 * The school comes from the route and is confirmed against the signed-in
 * member on every action, so a teacher can only ever reach their own school's
 * notes and their own school's classes.
 */
class ClassNoteController extends Controller
{
    /**
     * The largest document accepted, in kilobytes.
     *
     * PHP's upload_max_filesize and post_max_size must both exceed this, or an
     * oversized file is discarded before Laravel sees it and surfaces as the
     * baffling "the document field is required" rather than a size error.
     */
    private const MAX_KILOBYTES = 10240;

    public function __construct(private ClassNotePublisher $publisher) {}

    public function index(Request $request, School $school): View
    {
        $staff = $request->user('staff');

        $this->confirmSchool($school, $staff->school_id);

        $notes = ClassNote::forSchool($school)
            ->where('staff_id', $staff->id)
            ->with('classes')
            ->latest()
            ->paginate(10);

        // How many pupils each class holds, so the teacher can see who they
        // are about to send to, and see that a class is empty before they
        // wonder why nobody replied.
        $rollCall = Student::query()
            ->where('school_id', $school->id)
            ->where('is_active', true)
            ->selectRaw('class_name, COUNT(*) as total')
            ->groupBy('class_name')
            ->pluck('total', 'class_name');

        return view('staff.class-notes.index', [
            'school' => $school,
            'staff' => $staff,
            'notes' => $notes,
            'classOptions' => $school->configuredClassNames(),
            'rollCall' => $rollCall,

            // Their own classes, so the ones they teach can be marked out in a
            // long list. It is a convenience, not a restriction, see the
            // validation rule, which admits any class this school has.
            'myClassNames' => $staff->scorableClassNames(),

            'subjects' => $staff->subjectAssignments()->pluck('subject')->unique()->sort()->values(),
            'maxUploadLabel' => self::maxUploadLabel(),
            // Basic has no student portal for a note to arrive in. The page
            // says so plainly rather than letting a teacher upload into
            // silence and wonder why no pupil ever mentions it.
            'hasStudentPortal' => $school->hasPortalAccounts(),
        ]);
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $staff = $request->user('staff');

        $this->confirmSchool($school, $staff->school_id);

        $validated = $request->validate([
            'title' => ['required', 'string', 'max:150'],
            'subject' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:2000'],

            'class_names' => ['required', 'array', 'min:1'],

            // EVERY selected class must be one this school has. The list is
            // rebuilt from the school here rather than trusted from the form,
            // so a hand-edited checkbox naming another school's class is a
            // validation failure and never reaches the publisher.
            'class_names.*' => ['required', 'string', Rule::in($school->configuredClassNames())],

            // Word documents only. `mimetypes` reads the file's own content
            // rather than its name, and `mimes` covers the extension, a
            // renamed .exe satisfies neither.
            'document' => [
                'required',
                'file',
                'mimes:doc,docx',
                'mimetypes:application/msword,application/vnd.openxmlformats-officedocument.wordprocessingml.document',
                'max:'.self::MAX_KILOBYTES,
            ],
        ], [
            'class_names.required' => 'Choose at least one class to send this note to.',
            'class_names.*.in' => 'One of the classes you chose is not a class at this school.',
            'document.mimes' => 'The class note must be a Microsoft Word document (.doc or .docx).',
            'document.mimetypes' => 'That file is not a Microsoft Word document, whatever it is named.',
            'document.max' => 'The document must be no larger than '.self::maxUploadLabel().'.',
        ]);

        $result = $this->publisher->publish(
            school: $school,
            staff: $staff,
            document: $request->file('document'),
            classNames: $validated['class_names'],
            title: $validated['title'],
            subject: $validated['subject'] ?? null,
            description: $validated['description'] ?? null,
        );

        return back()->with('status', $this->confirmation($result));
    }

    /**
     * The teacher's own copy of what they sent.
     */
    public function download(Request $request, School $school, ClassNote $note): StreamedResponse
    {
        $staff = $request->user('staff');

        $this->confirmSchool($school, $staff->school_id);

        // The note's school, not the route's: a uuid from another school must
        // not become readable by pairing it with your own school's segment.
        abort_unless($note->school_id === $staff->school_id, 403);
        abort_unless($note->fileExists(), 404);

        return Storage::disk(ClassNote::DISK)->download($note->path, $note->original_name);
    }

    /**
     * Withdraw a note.
     *
     * Only the member who sent it, because a teacher who attached the wrong
     * document needs to take it back and nobody else should be able to remove
     * another teacher's material. The file goes with the row; the
     * notifications already delivered stay, because they are a record of what
     * happened.
     */
    public function destroy(Request $request, School $school, ClassNote $note): RedirectResponse
    {
        $staff = $request->user('staff');

        $this->confirmSchool($school, $staff->school_id);

        abort_unless($note->school_id === $staff->school_id, 403);
        abort_unless($note->staff_id === $staff->id, 403);

        Storage::disk(ClassNote::DISK)->delete($note->path);
        $note->delete();

        return back()->with('status', "“{$note->title}” was withdrawn and is no longer visible to students.");
    }

    /**
     * The school in the address and the school of the person signed in must be
     * the same school.
     */
    private function confirmSchool(School $school, ?int $staffSchoolId): void
    {
        abort_if($staffSchoolId === null, 403);
        abort_unless($school->id === $staffSchoolId, 403);
    }

    /**
     * @param  array{note: ClassNote, classNames: list<string>, notified: int}  $result
     */
    private function confirmation(array $result): string
    {
        $classes = implode(', ', $result['classNames']);
        $count = $result['notified'];

        if ($count === 0) {
            return "“{$result['note']->title}” was sent to {$classes}. There are no active students in "
                .(count($result['classNames']) === 1 ? 'that class' : 'those classes').' yet, so nobody has been notified.';
        }

        return sprintf(
            '“%s” was sent to %s. %d student%s notified.',
            $result['note']->title,
            $classes,
            $count,
            $count === 1 ? '' : 's',
        );
    }

    public static function maxUploadLabel(): string
    {
        return (int) (self::MAX_KILOBYTES / 1024).'MB';
    }
}
