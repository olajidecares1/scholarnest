<?php

namespace App\Services\Uploads;

use App\Support\StoredUpload;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\File;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

/**
 * The one way uploaded files are written, read back as local files, and
 * addressed.
 *
 * THE TWO DISKS. Everything uploaded lives on one of two named disks:
 *
 *   "public" - meant to be seen by anyone: school logos, website, gallery,
 *              news and facility images, CBT question images. Addressed with
 *              publicUrl().
 *   "local"  - never directly reachable: photographs of people, signatures,
 *              stamps, receipts, class notes, CBT source documents, report
 *              evidence. Served only by controllers that check who is asking.
 *
 * On a laptop both are directories under storage/. On Laravel Cloud the
 * filesystem is wiped by every deploy and differs between instances, so both
 * must be object storage - see config/filesystems.php and docs/PRODUCTION.md.
 * Nothing here assumes either: every write and read goes through the disk,
 * never through a filesystem path, so the same code is correct on both.
 *
 * WHAT USED TO GO WRONG, and why this class exists:
 *
 *  - Fourteen upload sites each stored the file and then "optimised" it in
 *    place through Storage::disk()->path(). That only works on a local disk,
 *    and the optimiser destroyed PNG transparency and left phone photographs
 *    sideways. Images now go through ImageProcessor BEFORE they are written.
 *  - On Laravel Cloud without object storage, uploads reported success and
 *    were never reachable again. Now the upload is refused, with a message,
 *    until storage is configured - a clear failure instead of a silent one.
 *  - PDFs and ID cards read images through ->path(), which does not exist on
 *    object storage. localPath() now provides a real file from either kind.
 */
class UploadStorage
{
    /**
     * Temporary local copies made during this process, deleted when it ends.
     *
     * @var array<string, string>
     */
    private static array $materialized = [];

    private static bool $cleanupRegistered = false;

    public function __construct(private readonly ImageProcessor $images) {}

    /**
     * Process an uploaded image and store it.
     *
     * @param  string  $field  The form field, so a problem is reported beside it.
     *
     * @throws ValidationException when the image cannot be accepted or stored.
     */
    public function storeImage(UploadedFile $file, string $disk, string $directory, ImageProfile $profile, string $field, ?string $stem = null): StoredImage
    {
        $this->assertPersistent($disk, $field);

        try {
            $image = $this->images->process((string) $file->getRealPath(), $profile);
        } catch (ImageRejected $e) {
            throw ValidationException::withMessages([$field => $e->getMessage()]);
        }

        $path = $this->pathFor($directory, ($stem ?? (string) Str::uuid()).'.'.$image->extension);

        $this->write($disk, $path, $image->bytes, $field);

        return new StoredImage($path, $image->width, $image->height, strlen($image->bytes), $image->mimeType);
    }

    /**
     * Store an uploaded file exactly as it arrived - a document or a video.
     *
     * The name is generated (App\Support\StoredUpload): a random stem and an
     * extension decided from the content against an allowlist. The uploader's
     * own filename is never used on disk.
     *
     * @throws ValidationException when it cannot be stored.
     */
    public function storeFile(UploadedFile $file, string $disk, string $directory, string $field): string
    {
        $this->assertPersistent($disk, $field);

        $path = $this->pathFor($directory, StoredUpload::name($file));
        $stream = @fopen((string) $file->getRealPath(), 'rb');

        if ($stream === false) {
            throw ValidationException::withMessages([$field => 'The file could not be read. Please try uploading it again.']);
        }

        try {
            $written = Storage::disk($disk)->writeStream($path, $stream);
        } finally {
            if (is_resource($stream)) {
                fclose($stream);
            }
        }

        if (! $written) {
            $this->reportWriteFailure($disk, $path);

            throw ValidationException::withMessages([$field => 'The file could not be saved. Please try again in a moment.']);
        }

        return $path;
    }

    /**
     * Store bytes the application generated itself - a drawn signature, an
     * extracted stamp, an image pulled out of a document.
     *
     * @throws \RuntimeException when storage is unavailable or the write fails.
     */
    public function putContents(string $disk, string $path, string $bytes): void
    {
        if (! self::isPersistent($disk)) {
            $this->reportNotPersistent($disk);

            throw new \RuntimeException(self::NOT_PERSISTENT_MESSAGE);
        }

        if (! Storage::disk($disk)->put($path, $bytes)) {
            $this->reportWriteFailure($disk, $path);

            throw new \RuntimeException('The file could not be saved. Please try again in a moment.');
        }
    }

    public function delete(string $disk, ?string $path): void
    {
        if ($path) {
            Storage::disk($disk)->delete($path);
        }
    }

    /**
     * The browser address of a file on the public disk.
     *
     * Built from the disk's configuration at the moment of display, never
     * stored: the database holds only the path, so the same row is correct on
     * a laptop, on a server with storage:link, and on object storage.
     */
    public static function publicUrl(?string $path): ?string
    {
        return $path ? Storage::disk('public')->url($path) : null;
    }

    /**
     * A real file on this machine holding the stored file, or null if there is
     * none - for code that genuinely needs a path: dompdf, GD, document text
     * extraction.
     *
     * A local disk answers with its own path. Any other disk is copied to a
     * temporary file inside the application (dompdf's chroot), deleted when
     * the request or command finishes and pruned after an hour regardless.
     */
    public function localPath(string $disk, ?string $path): ?string
    {
        if (! $path) {
            return null;
        }

        $storage = Storage::disk($disk);

        if (config("filesystems.disks.{$disk}.driver") === 'local') {
            return $storage->exists($path) ? $storage->path($path) : null;
        }

        $key = $disk.'|'.$path;

        if (isset(self::$materialized[$key]) && is_file(self::$materialized[$key])) {
            return self::$materialized[$key];
        }

        $directory = storage_path('framework/cache/stored-files');
        File::ensureDirectoryExists($directory);
        $this->pruneStaleCopies($directory);

        $extension = strtolower(pathinfo($path, PATHINFO_EXTENSION)) ?: 'bin';
        $local = $directory.'/'.hash('sha256', $key).'.'.preg_replace('/[^a-z0-9]/', '', $extension);

        $source = $storage->readStream($path);

        if (! is_resource($source)) {
            return null;
        }

        $target = fopen($local, 'wb');

        try {
            stream_copy_to_stream($source, $target);
        } finally {
            fclose($target);
            fclose($source);
        }

        self::$materialized[$key] = $local;
        $this->registerCleanup();

        return $local;
    }

    /**
     * Whether a file written to this disk will still be there tomorrow.
     *
     * False only on Laravel Cloud with a local-directory disk: that directory
     * is wiped by the next deploy, differs between instances, and is not
     * served. Everywhere else a local disk is a real, lasting directory.
     */
    public static function isPersistent(string $disk): bool
    {
        return ! (laravel_cloud() && config("filesystems.disks.{$disk}.driver") === 'local');
    }

    public const NOT_PERSISTENT_MESSAGE = 'File uploads are not available yet: storage for uploaded files has not been set up on this server. '
        .'Nothing was saved. Please contact AkademicNest support.';

    private function assertPersistent(string $disk, string $field): void
    {
        if (self::isPersistent($disk)) {
            return;
        }

        $this->reportNotPersistent($disk);

        throw ValidationException::withMessages([$field => self::NOT_PERSISTENT_MESSAGE]);
    }

    private function write(string $disk, string $path, string $bytes, string $field): void
    {
        if (Storage::disk($disk)->put($path, $bytes)) {
            return;
        }

        $this->reportWriteFailure($disk, $path);

        throw ValidationException::withMessages([$field => 'The image could not be saved. Please try again in a moment.']);
    }

    private function pathFor(string $directory, string $name): string
    {
        return trim($directory, '/').'/'.$name;
    }

    private function reportNotPersistent(string $disk): void
    {
        Log::critical("Upload refused: the \"{$disk}\" disk is a local directory on Laravel Cloud, where it is wiped by every deploy. Attach an object storage bucket with the disk name \"{$disk}\". See docs/PRODUCTION.md.");
    }

    private function reportWriteFailure(string $disk, string $path): void
    {
        Log::error("Upload could not be written to the \"{$disk}\" disk.", ['path' => $path]);
    }

    private function registerCleanup(): void
    {
        if (self::$cleanupRegistered) {
            return;
        }

        self::$cleanupRegistered = true;

        app()->terminating(function () {
            self::releaseLocalCopies();
        });
    }

    /**
     * Delete every temporary copy made so far. Queue jobs call this when done,
     * since a worker process does not "terminate" between jobs.
     */
    public static function releaseLocalCopies(): void
    {
        foreach (self::$materialized as $local) {
            if (is_file($local)) {
                @unlink($local);
            }
        }

        self::$materialized = [];
    }

    private function pruneStaleCopies(string $directory): void
    {
        foreach (glob($directory.'/*') ?: [] as $file) {
            if (is_file($file) && filemtime($file) < time() - 3600) {
                @unlink($file);
            }
        }
    }
}
