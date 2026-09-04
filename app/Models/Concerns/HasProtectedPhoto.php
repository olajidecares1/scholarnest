<?php

namespace App\Models\Concerns;

use App\Models\Guardian;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use Illuminate\Contracts\Filesystem\Filesystem;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\URL;

/**
 * A photograph of a person, kept out of public storage.
 *
 * Four models carry one - Student, Staff, Guardian and User - and they used to
 * do it four times over, each writing the same two methods against the public
 * disk. Sharing it here is not only tidier: it means the disk is named ONCE, so
 * a fifth model added later cannot quietly put a person's photograph back on
 * the public one.
 *
 * See App\Http\Controllers\ProtectedMediaController for why the URL is signed
 * rather than session-checked.
 */
trait HasProtectedPhoto
{
    /**
     * How long a minted photograph link stays valid.
     *
     * Long enough that a slow page, a re-render, or somebody reading a report
     * card carefully does not lose the image; short enough that an address
     * captured from a screenshot or a referrer header is worthless by the time
     * anybody tries it.
     */
    public const PHOTO_URL_MINUTES = 30;

    /**
     * The URL a browser should fetch this photograph from, or null when there
     * is none to fetch.
     *
     * A fresh signature every call, so the clock starts when the page is
     * rendered rather than when the photograph was uploaded.
     */
    public function photoUrl(): ?string
    {
        if (! $this->photo_path) {
            return null;
        }

        return URL::temporarySignedRoute('media.photo', now()->addMinutes(self::PHOTO_URL_MINUTES), [
            'subject' => $this->photoSubject(),
            'uuid' => $this->uuid,
        ]);
    }

    /**
     * Absolute local path, for dompdf views only.
     *
     * dompdf has `enable_remote` off, so it can never follow photoUrl() - it
     * reads local files within its chroot instead. That is why moving these off
     * the public disk did not break a single PDF: nothing in a PDF ever fetched
     * the URL.
     */
    public function photoAbsolutePath(): ?string
    {
        $disk = $this->photoDisk();

        return $this->photo_path && $disk->exists($this->photo_path)
            ? $disk->path($this->photo_path)
            : null;
    }

    /**
     * Named in one place so nothing has to remember which disk holds people.
     */
    public function photoDisk(): Filesystem
    {
        return Storage::disk('local');
    }

    /**
     * The directory these are written to, and the URL segment they are served
     * under - the same word, so a path and a link cannot drift apart.
     */
    public function photoSubject(): string
    {
        return match (static::class) {
            Student::class => 'student',
            Staff::class => 'staff',
            Guardian::class => 'guardian',
            User::class => 'user',
        };
    }
}
