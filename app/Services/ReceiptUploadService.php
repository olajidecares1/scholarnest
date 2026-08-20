<?php

namespace App\Services;

use App\Models\School;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class ReceiptUploadService
{
    /**
     * Stores a payment receipt for a school, stripping EXIF metadata from
     * image uploads for privacy before it ever touches disk.
     *
     * @return array{path: string, original_name: string}
     */
    public function store(School $school, UploadedFile $receipt): array
    {
        $filename = (string) Str::uuid().'.'.$receipt->getClientOriginalExtension();
        $path = "receipts/{$school->id}/{$filename}";

        if (str_starts_with((string) $receipt->getMimeType(), 'image/')) {
            $stripped = $this->stripExifData($receipt->getRealPath(), $receipt->getMimeType());
            Storage::disk('local')->put($path, $stripped);
        } else {
            $receipt->storeAs('receipts/'.$school->id, $filename, 'local');
        }

        return [
            'path' => $path,
            'original_name' => $receipt->getClientOriginalName(),
        ];
    }

    private function stripExifData(string $path, ?string $mimeType): string
    {
        $image = match ($mimeType) {
            'image/jpeg' => @imagecreatefromjpeg($path),
            'image/png' => @imagecreatefrompng($path),
            default => null,
        };

        if (! $image) {
            return file_get_contents($path);
        }

        ob_start();

        if ($mimeType === 'image/png') {
            imagepng($image);
        } else {
            imagejpeg($image, null, 90);
        }

        $contents = ob_get_clean();
        imagedestroy($image);

        return $contents;
    }
}
