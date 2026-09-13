<?php

namespace App\Http\Controllers;

use App\Enums\UserRole;
use App\Models\MisconductReport;
use App\Models\School;
use App\Models\User;
use App\Notifications\MisconductReportSubmittedNotification;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;

/**
 * Anyone can post here, which decides almost everything about it.
 *
 * A neighbour who saw a pupil in uniform doing something they should not is
 * not going to make an account first, so this endpoint is open. Being open is
 * exactly why it is written the way it is:
 *
 *   - The SCHOOL comes from the route's tenant binding, never from a field in
 *     the form. There is no school_id to point at somebody else's school.
 *   - Attachments go to the PRIVATE disk, are named by us, and are given an
 *     extension derived from their content. A photograph of a child is not
 *     something to leave at a guessable public URL.
 *   - Rate limited and honeypotted at the route, because an open endpoint
 *     that writes files is the first thing a bot finds.
 *   - Nothing the reporter types is ever echoed back into a page unescaped.
 */
class PublicMisconductReportController extends Controller
{
    /**
     * The largest attachment we will take, in bytes.
     *
     * Five megabytes covers a phone photograph comfortably and a short clip at
     * sensible quality. It is also the honest limit: a two-minute video that
     * fits in 5MB is a two-minute video worth watching.
     */
    private const MAX_BYTES = 5 * 1024 * 1024;

    private const MAX_FILES = 8;

    /**
     * Evidence is now REQUIRED, and at least one file has to arrive.
     *
     * A written report with nothing attached left the school investigating a
     * paragraph. Asking for a photograph is the difference between "somebody
     * says something happened" and something the school can act on - and
     * anybody close enough to report a pupil's conduct is close enough to
     * photograph it.
     */
    private const MIN_FILES = 1;

    /*
     * The three limits, readable from the form.
     *
     * The page needs them to preview, count and refuse before anything is
     * uploaded, and a form that hard-codes its own copy is a form that
     * disagrees with the server the day one of these changes. There is one
     * definition and the markup reads it.
     */
    public static function minFiles(): int
    {
        return self::MIN_FILES;
    }

    public static function maxFiles(): int
    {
        return self::MAX_FILES;
    }

    public static function maxBytes(): int
    {
        return self::MAX_BYTES;
    }

    public function store(Request $request, School $school): RedirectResponse
    {
        $validated = $request->validate([
            'reporter_name' => ['required', 'string', 'min:2', 'max:120'],

            // Optional on purpose. The people who use this form are neighbours
            // and shopkeepers, and requiring them to summarise before they are
            // allowed to write is how a report goes unsent. The inbox derives a
            // topic from the description when this is blank.
            'subject' => ['nullable', 'string', 'max:180'],

            'description' => ['required', 'string', 'min:10', 'max:2000'],
            'location' => ['nullable', 'string', 'max:180'],

            'attachments' => ['required', 'array', 'min:'.self::MIN_FILES, 'max:'.self::MAX_FILES],

            // mimetypes, not mimes: the type is read from the file's content
            // rather than trusted from the name it arrived with.
            'attachments.*' => [
                'file',
                'max:'.(int) (self::MAX_BYTES / 1024),
                'mimetypes:image/jpeg,image/png,image/webp,video/mp4,video/quicktime,video/webm',
            ],
        ], [
            'reporter_name.required' => 'Please tell us your name.',
            'description.required' => 'Please describe what you saw.',

            // Both spellings of "you sent none": `required` fires when the
            // field is absent altogether, `min` when an empty array arrives.
            // A reporter who has just been refused needs the same sentence
            // either way.
            'attachments.required' => 'Please attach at least one photograph as evidence.',
            'attachments.min' => 'Please attach at least one photograph as evidence.',
            'attachments.max' => 'You can attach up to '.self::MAX_FILES.' files.',

            // Size and type get their own wording. A reporter who has just
            // filmed something needs to know which of the two stopped them.
            'attachments.*.max' => 'Each file must be 5MB or smaller.',
            'attachments.*.mimetypes' => 'Attach photographs (JPG, PNG or WebP) or video (MP4, MOV or WebM) only.',
        ]);

        $report = MisconductReport::create([
            // From the route's school binding. There is no school field in
            // the form to disagree with this.
            'school_id' => $school->id,
            'reporter_name' => $validated['reporter_name'],
            'subject' => $validated['subject'] ?? null,
            'description' => $validated['description'],
            'location' => $validated['location'] ?? null,
            'status' => MisconductReport::STATUS_NEW,
        ]);

        foreach ($request->file('attachments') ?? [] as $file) {
            $this->attach($report, $file);
        }

        // Told straight away. A report nobody knows about is a report
        // that sits unread, and the whole point of the form is that the
        // school hears about it the same day.
        User::query()
            ->where('school_id', $school->id)
            ->where('role', UserRole::SchoolAdmin)
            ->get()
            ->each(fn (User $admin) => $admin->notify(new MisconductReportSubmittedNotification($report)));

        return back()
            ->with('misconduct_status', 'Thank you. Your report has been sent to the school and will be reviewed.')
            ->withFragment('contact');
    }

    /**
     * Store one attachment on the private disk.
     */
    private function attach(MisconductReport $report, UploadedFile $file): void
    {
        // Named by us, with an extension decided from the file's CONTENT -
        // see App\Support\StoredUpload. The reporter's own filename is never
        // written to disk, only recorded, so a school can see what they
        // called it.
        //
        // A photograph goes through the image processor, which removes its
        // EXIF - a phone photo carries the GPS position it was taken at, and
        // a reporter's location is exactly what they may need kept from the
        // school - and stores it upright. A video is stored as it arrived.
        $uploads = app(UploadStorage::class);
        $directory = 'misconduct-reports/'.$report->uuid;

        if (str_starts_with((string) $file->getMimeType(), 'image/')) {
            $image = $uploads->storeImage($file, 'local', $directory, ImageProfile::Website, 'attachments');
            [$path, $mime, $size] = [$image->path, $image->mimeType, $image->size];
        } else {
            $path = $uploads->storeFile($file, 'local', $directory, 'attachments');
            [$mime, $size] = [(string) $file->getMimeType(), (int) $file->getSize()];
        }

        $report->attachments()->create([
            'path' => $path,
            'original_name' => mb_substr($file->getClientOriginalName(), 0, 180),
            'mime_type' => $mime,
            'size_bytes' => $size,
        ]);
    }
}
