<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\School;
use App\Rules\UploadedImage;
use App\Services\StampImage;
use App\Services\Uploads\ImageProcessor;
use App\Services\Uploads\ImageRejected;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly UploadStorage $uploads) {}

    public function edit(Request $request): View
    {
        return view('school-admin.settings.edit', [
            'school' => $request->user()->school,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $wantsAutoGeneration = $request->boolean('auto_generate_admission_numbers') || $request->boolean('auto_generate_staff_ids');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'current_session' => ['nullable', 'string', 'max:20'],

            // On every plan. These print on result letterheads and on the back
            // of ID cards, and until now they lived only on the website, a
            // Standard and Exclusive feature, so a Basic school had no way to
            // give an address at all.
            'contact_address' => ['nullable', 'string', 'max:500'],
            'contact_phone' => ['nullable', 'string', 'max:60'],
            'contact_email' => ['nullable', 'email', 'max:255'],

            // The two lines of words a school prints on its own documents: the
            // motto under its name on a letterhead and an ID card, the values
            // along the foot of a report card. On every plan, for the same
            // reason the address is, see App\Support\SchoolMotto.
            'motto' => ['nullable', 'string', 'max:160'],
            'core_values' => ['nullable', 'string', 'max:200'],

            // Social handles, on every plan. Accepted with or without a
            // scheme, schools write "facebook.com/ourschool" as often as they
            // paste a full address, and turned into a working link when it is
            // rendered, by App\Support\SchoolSocialLinks.
            'facebook_url' => ['nullable', 'string', 'max:255'],
            'instagram_url' => ['nullable', 'string', 'max:255'],
            'twitter_url' => ['nullable', 'string', 'max:255'],
            'tiktok_url' => ['nullable', 'string', 'max:255'],
            'youtube_url' => ['nullable', 'string', 'max:255'],
            'linkedin_url' => ['nullable', 'string', 'max:255'],
            'whatsapp_number' => ['nullable', 'string', 'max:60'],

            // The name printed under the Principal's ruled line. The
            // SIGNATURE is not settable here: School Admin is the Principal,
            // so it is registered against their own account through the
            // signature pad, one authoritative signature per school, not a
            // school column and an account record free to disagree.
            'principal_name' => ['nullable', 'string', 'max:150'],

            'logo' => UploadedImage::rules(ImageProfile::Logo),
            'favicon' => UploadedImage::rules(ImageProfile::Favicon),

            // The official stamp. On every plan, see School::hasStamp().
            'stamp' => UploadedImage::rules(ImageProfile::Signature),
            'remove_stamp' => ['nullable', 'boolean'],
            'school_code' => [
                $wantsAutoGeneration ? 'required' : 'nullable',
                'string',
                'max:20',
                Rule::unique('schools', 'school_code')->ignore($school->id),
            ],
        ], [
            'school_code.required' => 'Set a school code before turning on automatic ID generation.',
            'school_code.unique' => 'This school code is already in use by another school. Please choose a different one.',
        ]);

        // Grading is always automatic now, so there is nothing to read from
        // the form. Forced rather than left to whatever a school last saved,
        // so an old "off" cannot linger.
        $validated['automatic_grading'] = true;
        $validated['auto_generate_admission_numbers'] = $request->boolean('auto_generate_admission_numbers');
        $validated['auto_generate_staff_ids'] = $request->boolean('auto_generate_staff_ids');

        // Processed before storage, upright, within size, transparency kept,
        // metadata removed, see App\Services\Uploads\ImageProcessor.
        $logoPath = $request->hasFile('logo')
            ? $this->uploads->storeImage($request->file('logo'), 'public', 'school-logos', ImageProfile::Logo, 'logo')->path
            : null;

        $faviconPath = $request->hasFile('favicon')
            ? $this->uploads->storeImage($request->file('favicon'), 'public', 'school-favicons', ImageProfile::Favicon, 'favicon')->path
            : null;

        // The stamp is lifted off its paper before it is stored, see
        // App\Services\StampImage, and lands on the PRIVATE disk, like a
        // signature. A refusal is reported against the field rather than
        // thrown, so a school that photographed a blank sheet is told what
        // went wrong instead of meeting a 500.
        $stampPath = $school->stamp_path;

        if ($request->boolean('remove_stamp')) {
            $stampPath = null;
        }

        if ($request->hasFile('stamp')) {
            try {
                $stampPath = $this->storeStamp($school, $request->file('stamp'));
            } catch (\RuntimeException $e) {
                return back()->withErrors(['stamp' => $e->getMessage()])->withInput();
            }
        }

        $previousLogo = $school->logo_path;
        $previousFavicon = $school->favicon_path;

        $school->update([
            ...collect($validated)->except(['logo', 'favicon', 'stamp', 'remove_stamp'])->all(),
            'logo_path' => $logoPath ?: $school->logo_path,
            'favicon_path' => $faviconPath ?: $school->favicon_path,
            'stamp_path' => $stampPath,
        ]);

        // A replaced logo or favicon used to stay on disk for ever. Removed
        // only after the row points at its replacement, so a failure between
        // the two can never leave the school pointing at a deleted file.
        if ($logoPath && $previousLogo && $previousLogo !== $logoPath) {
            $this->uploads->delete('public', $previousLogo);
        }

        if ($faviconPath && $previousFavicon && $previousFavicon !== $faviconPath) {
            $this->uploads->delete('public', $previousFavicon);
        }

        return back()->with('status', 'Your school settings were updated.');
    }

    /**
     * Extract the stamp from what was uploaded and keep it privately.
     *
     * The uploaded photograph is never stored. What lands on disk is the mark
     * itself on transparency, trimmed to the ink, so it can sit over a report
     * card rather than on top of it as a white rectangle.
     *
     * PRIVATE DISK, like a signature. A stamp is what makes a document look
     * official; an address that serves a clean copy of one is an address for
     * forging the school's paperwork. Everything that prints it embeds it.
     *
     * @throws \RuntimeException when the image cannot be read or holds no stamp.
     */
    private function storeStamp(School $school, UploadedFile $file): string
    {
        // Upright first: a stamp photographed on a phone arrives with its
        // rotation in EXIF, and the ink detection reads raw pixels.
        try {
            $upright = app(ImageProcessor::class)->process((string) $file->getRealPath(), ImageProfile::Signature)->bytes;
        } catch (ImageRejected $e) {
            throw new \RuntimeException($e->getMessage());
        }

        $png = app(StampImage::class)->extract($upright);

        $path = 'school-stamps/'.Str::uuid().'.png';

        $this->uploads->putContents('local', $path, $png);

        // Only once the replacement is safely written. Deleting first would
        // leave a school with no stamp at all if the new one failed.
        $this->uploads->delete('local', $school->stamp_path);

        return $path;
    }
}
