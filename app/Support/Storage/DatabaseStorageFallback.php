<?php

namespace App\Support\Storage;

use Illuminate\Contracts\Foundation\Application;
use Illuminate\Filesystem\FilesystemAdapter;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Storage;
use League\Flysystem\Filesystem;

/**
 * Keep uploads in the database on Laravel Cloud when no bucket is attached.
 *
 * WHY. The "public" and "local" disks are directories by default. On Laravel
 * Cloud those directories are not served, differ between instances and are
 * wiped by every deploy, so until object storage buckets are attached, every
 * upload is lost and every image on the site is broken. Rather than refuse
 * uploads until somebody configures the dashboard, each such disk is switched
 * to the database, which every instance shares and every deploy keeps.
 *
 * WHEN. Only on Laravel Cloud, and only for a disk that is still a local
 * directory at the moment providers register. Laravel applies an attached
 * bucket before that (Illuminate\Foundation\Cloud::configureDisks, which runs
 * as soon as configuration loads), so a disk with a bucket is already "s3"
 * here and is left alone. A laptop, the test suite and an ordinary server with
 * storage:link are never touched.
 *
 * Decided at runtime, not in config/filesystems.php: configuration is cached
 * during the build, where Laravel Cloud's runtime variables may not be present.
 *
 * ATTACHING BUCKETS LATER. The disks become buckets and the files stored here
 * stay in the database, so run `php artisan uploads:move-to-buckets` after the
 * redeploy to copy them across.
 */
final class DatabaseStorageFallback
{
    public const DRIVER = 'database';

    /** @var list<string> */
    public const DISKS = ['public', 'local'];

    public static function registerDriver(Application $app): void
    {
        Storage::extend(self::DRIVER, function ($app, array $config) {
            $adapter = new DatabaseFilesystemAdapter(
                disk: $config['disk'],
                url: $config['url'] ?? null,
                defaultVisibility: $config['visibility'] ?? 'private',
            );

            return new FilesystemAdapter(new Filesystem($adapter, $config), $adapter, $config);
        });
    }

    /**
     * Switch every upload disk that would lose its files to the database.
     *
     * @return list<string> the disks that were switched
     */
    public static function apply(): array
    {
        if (! laravel_cloud()) {
            return [];
        }

        $switched = [];

        foreach (self::DISKS as $disk) {
            if (config("filesystems.disks.{$disk}.driver") !== 'local') {
                continue;
            }

            config(["filesystems.disks.{$disk}" => self::configFor($disk)]);
            $switched[] = $disk;
        }

        return $switched;
    }

    /**
     * Make sure, at the moment it matters, that no upload disk is a directory
     * Laravel Cloud will wipe.
     *
     * Called by UploadStorage before every write and before every answer to
     * "is this disk persistent". apply() at boot should already have switched
     * both disks, but a production school admin met "File uploads are not
     * available yet" on the stamp and the signature, both on the private disk,
     * while the public disk served uploads from the database. Rather than
     * depend on boot order, the check is repeated where the file is written,
     * and a disk still found wanting is switched then, and reported, so the
     * cause can be found in the logs.
     */
    public static function ensure(): void
    {
        $switched = self::apply();

        if ($switched === []) {
            return;
        }

        if (app()->resolved('filesystem')) {
            Storage::forgetDisk($switched);
        }

        if (app()->isBooted()) {
            Log::warning('Upload disks were still local directories on Laravel Cloud after boot and have been switched to the database now.', [
                'disks' => $switched,
            ]);
        }
    }

    /**
     * @return array<string, mixed>
     */
    public static function configFor(string $disk): array
    {
        return [
            'driver' => self::DRIVER,
            'disk' => $disk,
            'visibility' => $disk === 'public' ? 'public' : 'private',

            // Public files are served by StoredFileController at /files/...;
            // the private disk has no address at all, as before.
            'url' => $disk === 'public' ? rtrim((string) config('app.url'), '/').'/files' : null,

            'throw' => false,
            'report' => false,
        ];
    }
}
