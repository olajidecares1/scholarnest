<?php

namespace App\Services;

use App\Models\School;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\UploadedFile;

class ReceiptUploadService
{
    public function __construct(private readonly UploadStorage $uploads) {}

    /**
     * Stores a payment receipt for a school, on the private disk.
     *
     * A photographed receipt goes through the image processor, which removes
     * its EXIF metadata (a phone photo carries GPS coordinates) and, unlike
     * the stripping this used to do, applies its orientation first, so a
     * receipt photographed upright is not stored sideways and unreadable.
     * A PDF is stored exactly as uploaded.
     *
     * @return array{path: string, original_name: string}
     */
    public function store(School $school, UploadedFile $receipt): array
    {
        $directory = 'receipts/'.$school->id;

        $path = str_starts_with((string) $receipt->getMimeType(), 'image/')
            ? $this->uploads->storeImage($receipt, 'local', $directory, ImageProfile::Receipt, 'receipt')->path
            : $this->uploads->storeFile($receipt, 'local', $directory, 'receipt');

        return [
            'path' => $path,
            'original_name' => self::displayName($receipt->getClientOriginalName()),
        ];
    }

    /**
     * The uploader's filename, made safe to keep and to show.
     *
     * Only ever displayed (and offered as the download name); the stored file
     * has a generated name. Folder parts, control characters and quotes are
     * dropped, and it is cut short: the column holds 255 characters, and a
     * longer name used to fail the final step with a database error.
     */
    public static function displayName(?string $name): string
    {
        $name = basename(str_replace('\\', '/', (string) $name));
        $name = (string) preg_replace('/[\x00-\x1F\x7F"<>\\\\]/u', '', $name);
        $name = trim($name);

        if ($name === '') {
            return 'receipt';
        }

        if (mb_strlen($name) <= 150) {
            return $name;
        }

        $extension = mb_substr((string) pathinfo($name, PATHINFO_EXTENSION), 0, 10);

        return mb_substr(pathinfo($name, PATHINFO_FILENAME), 0, 140).($extension !== '' ? '.'.$extension : '');
    }
}
