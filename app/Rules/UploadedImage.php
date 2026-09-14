<?php

namespace App\Rules;

use App\Services\Uploads\ImageProcessor;
use App\Services\Uploads\ImageRejected;
use App\Support\Uploads\ImageProfile;
use Closure;
use Illuminate\Contracts\Validation\ValidationRule;
use Illuminate\Http\UploadedFile;

/**
 * An uploaded image this application can accept for a given use.
 *
 * Replaces the bare `image` + `max` pairs that were scattered across fourteen
 * forms with five different size limits. It checks what the file IS rather
 * than what it is called:
 *
 *  - the upload actually arrived, with a specific message when PHP dropped it
 *    for size, instead of "failed to upload";
 *  - its size is within the profile's limit;
 *  - its content is a JPEG, PNG, WebP, GIF or BMP image (or HEIC where the
 *    server can convert it), identified from the bytes, a PHP script renamed
 *    photo.jpg is not an image, whatever the name says;
 *  - it can actually be read, and is not so large in pixels that decoding it
 *    would exhaust memory.
 *
 * The full decode happens when it is stored (UploadStorage::storeImage), and a
 * failure there is reported against the same field.
 */
class UploadedImage implements ValidationRule
{
    public function __construct(private readonly ImageProfile $profile) {}

    public function validate(string $attribute, mixed $value, Closure $fail): void
    {
        $limitMb = (int) round($this->profile->maxKilobytes() / 1024);

        if (! $value instanceof UploadedFile) {
            $fail('Choose an image to upload.');

            return;
        }

        if (! $value->isValid()) {
            $fail(match ($value->getError()) {
                UPLOAD_ERR_INI_SIZE, UPLOAD_ERR_FORM_SIZE => "That image is larger than the server accepts. Please choose one under {$limitMb}MB.",
                UPLOAD_ERR_PARTIAL => 'The upload was interrupted before it finished. Please check your connection and try again.',
                UPLOAD_ERR_NO_FILE => 'Choose an image to upload.',
                default => 'The image could not be received. Please try again.',
            });

            return;
        }

        if ($value->getSize() > $this->profile->maxKilobytes() * 1024) {
            $fail("That image is larger than {$limitMb}MB. Please choose a smaller one.");

            return;
        }

        try {
            app(ImageProcessor::class)->inspect((string) $value->getRealPath());
        } catch (ImageRejected $e) {
            $fail($e->getMessage());
        }
    }

    /**
     * The rules for an image field, in one call.
     *
     * @return list<mixed>
     */
    public static function rules(ImageProfile $profile, bool $required = false): array
    {
        return [$required ? 'required' : 'nullable', new self($profile)];
    }
}
