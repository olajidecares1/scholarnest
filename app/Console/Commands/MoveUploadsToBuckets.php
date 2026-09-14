<?php

namespace App\Console\Commands;

use App\Models\StoredFile;
use App\Support\Storage\DatabaseStorageFallback;
use Illuminate\Console\Command;
use Illuminate\Support\Facades\Storage;
use Throwable;

/**
 * Copy uploads kept in the database into object storage buckets.
 *
 * Uploads live in the database while no bucket is attached (see
 * DatabaseStorageFallback). Once buckets are attached and the app redeployed,
 * each disk IS the bucket, and the files saved in the meantime are still in
 * the database, invisible to it. This moves them across, file by file,
 * checking each arrived intact before removing the database copy.
 *
 * Safe to run more than once, and safe to interrupt: a file is only removed
 * from the database after its copy in the bucket has been verified.
 */
class MoveUploadsToBuckets extends Command
{
    protected $signature = 'uploads:move-to-buckets
        {--keep : Copy to the buckets but leave the database copies in place}';

    protected $description = 'Move uploads kept in the database into the object storage buckets once they are attached';

    public function handle(): int
    {
        $failures = 0;
        $moved = 0;

        foreach (DatabaseStorageFallback::DISKS as $disk) {
            $count = StoredFile::where('disk', $disk)->count();

            if ($count === 0) {
                continue;
            }

            $driver = (string) config("filesystems.disks.{$disk}.driver");

            if (in_array($driver, [DatabaseStorageFallback::DRIVER, 'local'], true)) {
                $this->components->warn("The \"{$disk}\" disk is not a bucket yet ({$driver}). Attach a bucket named \"{$disk}\", redeploy, then run this again. {$count} file(s) left where they are.");

                continue;
            }

            $this->components->info("Moving {$count} file(s) to the \"{$disk}\" bucket");

            StoredFile::where('disk', $disk)->orderBy('id')->each(function (StoredFile $file) use ($disk, &$failures, &$moved) {
                try {
                    $stream = fopen('php://temp', 'w+b');
                    $file->copyRangeTo($stream);
                    rewind($stream);

                    $target = Storage::disk($disk);
                    $target->writeStream($file->path, $stream);
                    fclose($stream);

                    if (! $target->exists($file->path) || $target->size($file->path) !== $file->size) {
                        throw new \RuntimeException('the copy in the bucket does not match');
                    }

                    if (! $this->option('keep')) {
                        $file->delete();
                    }

                    $moved++;
                    $this->components->twoColumnDetail($file->path, '<fg=green>moved</>');
                } catch (Throwable $e) {
                    $failures++;
                    $this->components->twoColumnDetail($file->path, '<fg=red>FAILED</> '.$e->getMessage());
                }
            });
        }

        if ($moved === 0 && $failures === 0) {
            $this->components->info('Nothing to move.');
        } elseif ($failures === 0) {
            $this->components->info("{$moved} file(s) moved.");
        } else {
            $this->components->error("{$failures} file(s) could not be moved and are still in the database. Run the command again.");
        }

        return $failures === 0 ? self::SUCCESS : self::FAILURE;
    }
}
