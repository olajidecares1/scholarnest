<?php

namespace App\Console\Commands;

use App\Models\StoredFile;
use App\Services\Uploads\UploadStorage;
use App\Support\Storage\DatabaseStorageFallback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Schema;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Throwable;

/**
 * Whether uploaded files will actually survive, and actually load.
 *
 * Runnable from Laravel Cloud's Commands tab, where there is no shell to go and
 * look. It answers the questions that took a long time to answer by reading
 * page source when every uploaded image was broken in production:
 *
 *  - What is each disk really backed by, after Laravel Cloud has applied any
 *    bucket attached to it?
 *  - Will a file written now still be there after the next deploy?
 *  - Can it be written, read back and deleted - for real, not in theory?
 *  - For the public disk, what address will a browser be given?
 *  - (--references) Which rows in the database point at files that are gone,
 *    and so will show as broken until they are uploaded again?
 *
 * Exits non-zero when something is wrong, so it can gate a deploy.
 */
class CheckUploadStorage extends Command
{
    protected $signature = 'uploads:check
        {--references : Also check every stored file reference in the database for a missing file}';

    protected $description = 'Check that the upload disks are persistent and working, and optionally find database references to missing files';

    /**
     * Every column that holds an uploaded file's path, and the disk it lives on.
     *
     * @var list<array{0: string, 1: string, 2: string}>
     */
    private const REFERENCES = [
        ['schools', 'logo_path', 'public'],
        ['schools', 'favicon_path', 'public'],
        ['schools', 'stamp_path', 'local'],
        ['students', 'photo_path', 'local'],
        ['staff', 'photo_path', 'local'],
        ['guardians', 'photo_path', 'local'],
        ['users', 'photo_path', 'local'],
        ['signatures', 'path', 'local'],
        ['school_websites', 'hero_image_path', 'public'],
        ['school_websites', 'about_image_path', 'public'],
        ['school_websites', 'principal_photo_path', 'public'],
        ['school_websites', 'news_events_card_image_path', 'public'],
        ['school_websites', 'academics_card_image_path', 'public'],
        ['school_websites', 'about_card_image_path', 'public'],
        ['hero_slides', 'image_path', 'public'],
        ['news_posts', 'image_path', 'public'],
        ['school_facilities', 'image_path', 'public'],
        ['school_gallery_images', 'image_path', 'public'],
        ['id_card_templates', 'background_path', 'public'],
        ['cbt_questions', 'image_path', 'public'],
        ['cbt_test_questions', 'image_path', 'public'],
        ['media', 'path', 'public'],
        ['payments', 'receipt_path', 'local'],
        ['subscription_top_ups', 'receipt_path', 'local'],
        ['class_notes', 'path', 'local'],
        ['misconduct_report_attachments', 'path', 'local'],
        ['reports', 'media_path', 'local'],
        ['cbt_document_uploads', 'path', 'local'],
        ['cbt_test_document_uploads', 'path', 'local'],
    ];

    public function handle(): int
    {
        $healthy = true;

        $this->components->info('Upload storage'.(laravel_cloud() ? ' (running on Laravel Cloud)' : ''));

        foreach (['public', 'local'] as $disk) {
            $healthy = $this->checkDisk($disk) && $healthy;
        }

        if ($this->option('references')) {
            $healthy = $this->checkReferences() && $healthy;
        }

        $healthy
            ? $this->components->info('Uploads will be stored persistently and can be read back.')
            : $this->components->error('Uploads are NOT safe on this server. See the problems above and docs/PRODUCTION.md.');

        return $healthy ? self::SUCCESS : self::FAILURE;
    }

    private function checkDisk(string $disk): bool
    {
        $driver = (string) config("filesystems.disks.{$disk}.driver");
        $persistent = UploadStorage::isPersistent($disk);

        $this->components->twoColumnDetail("<fg=cyan>{$disk}</> disk", $driver);
        $this->components->twoColumnDetail('  survives a deploy', $persistent ? '<fg=green>yes</>' : '<fg=red>NO</>');

        if (! $persistent) {
            $this->components->warn("  The \"{$disk}\" disk is a directory Laravel Cloud wipes on every deploy. Attach an object storage bucket with the disk name \"{$disk}\", then redeploy.");
        }

        $inDatabase = Schema::hasTable('stored_files') ? StoredFile::where('disk', $disk)->count() : 0;

        if ($driver === DatabaseStorageFallback::DRIVER) {
            $this->components->twoColumnDetail('  files kept in the database', (string) $inDatabase);
            $this->line('    No bucket is attached, so uploads are kept in the database. That works; attach a bucket named "'.$disk.'" when you can, then run uploads:move-to-buckets.');
        } elseif ($inDatabase > 0) {
            $this->components->warn("  {$inDatabase} file(s) for this disk are still in the database from before a bucket was attached. Run `php artisan uploads:move-to-buckets`.");
        }

        $probe = '.upload-check/'.Str::uuid().'.txt';
        $contents = 'akademicnest upload check '.now()->toIso8601String();

        try {
            $storage = Storage::disk($disk);

            $written = $storage->put($probe, $contents);
            $read = $written ? $storage->get($probe) : null;
            $roundTrip = $written && $read === $contents;

            if ($disk === 'public' && $written) {
                $this->components->twoColumnDetail('  browser address', (string) UploadStorage::publicUrl($probe));
            }

            $storage->delete($probe);
            $deleted = $written && ! $storage->exists($probe);
        } catch (Throwable $e) {
            $this->components->twoColumnDetail('  write / read / delete', '<fg=red>FAILED</>');
            $this->components->error('  '.$e->getMessage());

            return false;
        }

        $this->components->twoColumnDetail('  write / read / delete', $roundTrip && $deleted ? '<fg=green>ok</>' : '<fg=red>FAILED</>');

        return $persistent && $roundTrip && $deleted;
    }

    private function checkReferences(): bool
    {
        $this->newLine();
        $this->components->info('Database references to uploaded files');

        $allPresent = true;

        foreach (self::REFERENCES as [$table, $column, $disk]) {
            if (! Schema::hasTable($table) || ! Schema::hasColumn($table, $column)) {
                continue;
            }

            $paths = DB::table($table)->whereNotNull($column)->where($column, '!=', '')->pluck($column);

            if ($paths->isEmpty()) {
                continue;
            }

            $missing = $paths->reject(fn (string $path) => Storage::disk($disk)->exists($path));

            $this->components->twoColumnDetail(
                "{$table}.{$column}",
                $missing->isEmpty()
                    ? "<fg=green>{$paths->count()} ok</>"
                    : "<fg=red>{$missing->count()} of {$paths->count()} missing</>",
            );

            if ($missing->isNotEmpty()) {
                $allPresent = false;
                $this->line('    e.g. '.$missing->take(3)->implode(', '));
            }
        }

        if (! $allPresent) {
            $this->components->warn('Files marked missing show as broken until they are uploaded again. They were most likely written to a disk that a deploy has since wiped.');
        }

        return $allPresent;
    }
}
