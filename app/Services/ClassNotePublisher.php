<?php

namespace App\Services;

use App\Models\ClassNote;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Notifications\NewClassNotePosted;
use App\Support\StoredUpload;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Sending one document to several classes at once.
 *
 * The whole of the feature's delivery rule lives here, in one place, so the
 * controller cannot be the thing that remembers to scope by school:
 *
 *     school -> staff -> class note -> selected classes -> pupils in them
 *
 * THE SCHOOL IS TAKEN FROM THE SIGNED-IN MEMBER, never from the request. The
 * class names are then intersected with that school's own classes, so a
 * submitted class this school does not have is dropped rather than stored -
 * a teacher at one school cannot name a class at another and reach its pupils.
 *
 * The file is written ONCE. Several classes receive the same row.
 */
class ClassNotePublisher
{
    public function __construct(private CbtDocxTextExtractor $extractor) {}

    /**
     * Store the document, record the classes, notify the pupils.
     *
     * @param  list<string>  $classNames  Already validated against the school.
     * @return array{note: ClassNote, classNames: list<string>, notified: int}
     */
    public function publish(
        School $school,
        Staff $staff,
        UploadedFile $document,
        array $classNames,
        string $title,
        ?string $subject,
        ?string $description,
    ): array {
        // Belt and braces over the validation rule that got us here. A class
        // this school does not have never reaches the database, whatever the
        // form said.
        $permitted = $this->permittedClasses($school, $classNames);

        abort_if($permitted === [], 422);

        $path = $document->storeAs(
            ClassNote::DIRECTORY,
            StoredUpload::name($document),
            ClassNote::DISK,
        );

        $note = DB::transaction(function () use ($school, $staff, $document, $path, $permitted, $title, $subject, $description) {
            $note = ClassNote::create([
                'school_id' => $school->id,
                'staff_id' => $staff->id,
                'title' => $title,
                'subject' => $subject,
                'description' => $description,
                'path' => $path,
                'original_name' => $document->getClientOriginalName(),
                'mime_type' => $document->getClientMimeType(),
                'size_bytes' => $document->getSize() ?: 0,
                'body_text' => $this->readText($path),
            ]);

            $note->classes()->createMany(
                array_map(fn (string $className) => ['class_name' => $className], $permitted),
            );

            return $note;
        });

        return [
            'note' => $note->load('classes'),
            'classNames' => $permitted,
            'notified' => $this->notify($note, $permitted),
        ];
    }

    /**
     * Every pupil in the selected classes is told, once each.
     *
     * A pupil in two of the selected classes - which the data allows, even if
     * the timetable does not - gets one notification rather than two, because
     * the recipients are collected across all the classes and then keyed by
     * id. The class named in each notification is the one that pupil is
     * actually in.
     *
     * @param  list<string>  $classNames
     * @return int Pupils notified.
     */
    private function notify(ClassNote $note, array $classNames): int
    {
        $recipients = Student::query()
            ->where('school_id', $note->school_id)
            ->whereIn('class_name', $classNames)
            ->where('is_active', true)
            ->get(['id', 'class_name', 'school_id'])
            ->unique('id');

        foreach ($recipients as $student) {
            $student->notify(new NewClassNotePosted($note, (string) $student->class_name));
        }

        return $recipients->count();
    }

    /**
     * The submitted classes that this school actually has.
     *
     * configuredClassNames() is the school's own list - the classes it has set
     * up, plus any its pupils are actually in - so the intersection is the
     * boundary. Comparison is exact, because that is how a class name is
     * matched to a pupil everywhere else in this application.
     *
     * @param  list<string>  $classNames
     * @return list<string>
     */
    public function permittedClasses(School $school, array $classNames): array
    {
        $schoolClasses = Collection::make($school->configuredClassNames());

        return Collection::make($classNames)
            ->map(fn (mixed $name) => is_string($name) ? trim($name) : '')
            ->filter()
            ->unique()
            ->filter(fn (string $name) => $schoolClasses->contains($name))
            ->values()
            ->all();
    }

    /**
     * The document's words, so a pupil can read and copy the note without
     * owning Word.
     *
     * Done once here rather than on every view: parsing a docx per page load
     * would be paid again by every pupil in the year group, every time they
     * open it.
     *
     * A FAILURE IS NOT AN ERROR. A legacy .doc cannot be read at all - its
     * binary format is not a zip of XML - and a docx can be malformed. Either
     * way the note is still delivered and still downloadable; only the
     * on-screen text is missing, and the pupil's page says why.
     */
    private function readText(string $path): ?string
    {
        try {
            $absolute = Storage::disk(ClassNote::DISK)->path($path);
            $text = trim($this->extractor->extract($absolute)['text'] ?? '');

            return $text === '' ? null : $text;
        } catch (Throwable $e) {
            Log::info('Class note text could not be extracted.', [
                'path' => $path,
                'reason' => $e->getMessage(),
            ]);

            return null;
        }
    }
}
