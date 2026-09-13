<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Facades\Storage;

/**
 * The bytes of the platform logo or favicon, held in the database.
 *
 * The "public" disk cannot be relied on to serve them: in production it is a
 * directory that is not web-reachable and is wiped by every deploy. See the
 * create_branding_images_table migration.
 *
 * Addressed by the same path the setting stores, and served by
 * BrandingImageController at /branding/{file}.
 */
class BrandingImage extends Model
{
    public const DIRECTORY = 'branding';

    /**
     * What may be served back. The upload rules admit only these, and the
     * controller answers with this type rather than whatever is in the row, so
     * nothing stored here can ever be handed to a browser as HTML.
     *
     * @var array<string, string>
     */
    public const TYPES = [
        'png' => 'image/png',
        'jpg' => 'image/jpeg',
        'jpeg' => 'image/jpeg',
        'ico' => 'image/x-icon',
        'webp' => 'image/webp',
        'gif' => 'image/gif',
    ];

    /**
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'mime_type',
        'data',
    ];

    /**
     * Keep the stored bytes of a logo or favicon under $path.
     */
    public static function remember(string $path, string $bytes): void
    {
        self::query()->updateOrCreate(
            ['path' => $path],
            [
                'mime_type' => self::typeFor($path),
                'data' => base64_encode($bytes),
            ],
        );
    }

    public static function forget(?string $path): void
    {
        if ($path) {
            self::query()->where('path', $path)->delete();
        }
    }

    /**
     * The address a browser loads this image from.
     *
     * Through the application, not the disk, so it resolves on every replica
     * and after every deploy. Relative to the current host, so it works on a
     * school's subdomain or custom domain as well as the platform's own.
     */
    public static function url(string $path): string
    {
        return route('branding.show', ['file' => basename($path)], false);
    }

    /**
     * The bytes for $path: from the database, or from the disk for an upload
     * that predates this table and is still there - a laptop, or a server where
     * storage:link works.
     */
    public static function contents(string $path): ?string
    {
        $data = self::query()->where('path', $path)->value('data');

        if ($data !== null) {
            $bytes = base64_decode($data, true);

            return $bytes === false ? null : $bytes;
        }

        return Storage::disk('public')->exists($path) ? Storage::disk('public')->get($path) : null;
    }

    public static function typeFor(string $path): string
    {
        return self::TYPES[strtolower(pathinfo($path, PATHINFO_EXTENSION))] ?? 'application/octet-stream';
    }
}
