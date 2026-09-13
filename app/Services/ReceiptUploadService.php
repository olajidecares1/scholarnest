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
     * its EXIF metadata (a phone photo carries GPS coordinates) and - unlike
     * the stripping this used to do - applies its orientation first, so a
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
            'original_name' => $receipt->getClientOriginalName(),
        ];
    }
}
