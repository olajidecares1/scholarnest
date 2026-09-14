<?php

namespace App\Services\Recruitment;

use App\Models\JobPosting;
use App\Models\Setting;
use App\Services\Uploads\UploadStorage;
use App\Support\SchoolContact;
use App\Support\ThemePreset;
use Endroid\QrCode\Builder\Builder;
use Endroid\QrCode\Writer\PngWriter;
use GdImage;
use Illuminate\Support\Str;

/**
 * The picture a school shares to advertise a vacancy.
 *
 * A LINK CANNOT LIVE INSIDE A PICTURE. No social network makes the pixels of an
 * uploaded image clickable, so "an image that takes applicants to the job" is
 * delivered two ways, and this draws both:
 *
 *  - story(): 1080 x 1350, the shape Instagram, WhatsApp Status and Facebook
 *    feeds show largest. The vacancy's own address is printed on it with a QR
 *    code, so somebody who only ever sees the image, a forwarded WhatsApp
 *    picture, an Instagram post, can still reach that exact vacancy.
 *  - card(): 1200 x 630, the link-preview shape. It is the og:image of the
 *    vacancy's page, so pasting the link anywhere shows the school's logo,
 *    name, the job title and the job image, and tapping it opens the vacancy.
 *
 * Both are built from the school's own profile, logo, name, address, and its
 * brand colour, so the image cannot drift from the school it advertises.
 */
class JobShareImage
{
    private const FONT_REGULAR = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans.ttf';

    private const FONT_BOLD = 'vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf';

    public function __construct(private readonly UploadStorage $uploads) {}

    /** Portrait, with the address and a QR code. JPEG bytes. */
    public function story(JobPosting $job): string
    {
        $width = 1080;
        $height = 1350;
        $pad = 64;
        $imageHeight = 700;

        $canvas = $this->canvas($width, $height);
        $brand = $this->brandColour($job);

        $this->drawHero($canvas, $job, 0, 0, $width, $imageHeight, $brand);
        $this->drawHiringBadge($canvas, $pad, $pad, $brand, 26);

        $y = $imageHeight + 52;
        $y = $this->drawSchoolHeader($canvas, $job, $pad, $y, $width - $pad * 2, 88, 30, 20);

        $y += 36;
        $y = $this->drawWrapped($canvas, $job->title, self::FONT_BOLD, 50, $this->rgb($canvas, [17, 24, 39]), $pad, $y, $width - $pad * 2, 3, 1.18);

        $y += 18;
        $this->drawWrapped($canvas, $this->metaLine($job), self::FONT_REGULAR, 24, $this->rgb($canvas, [75, 85, 99]), $pad, $y, $width - $pad * 2, 2, 1.35);

        // The way in, for somebody who only has the picture.
        $qrSize = 230;
        $qrX = $width - $pad - $qrSize;
        $qrY = $height - $pad - $qrSize;
        $this->drawQr($canvas, $job->publicUrl(), $qrX, $qrY, $qrSize);

        $textWidth = $qrX - $pad - 36;
        $textY = $qrY + 24;
        $textY = $this->drawWrapped($canvas, 'Scan or visit to apply', self::FONT_BOLD, 28, $this->allocate($canvas, $brand), $pad, $textY, $textWidth, 1, 1.2);
        $this->drawWrapped($canvas, $this->displayUrl($job), self::FONT_REGULAR, 21, $this->rgb($canvas, [55, 65, 81]), $pad, $textY + 14, $textWidth, 5, 1.4, breakAnywhere: true);

        return $this->jpeg($canvas);
    }

    /** Landscape link-preview card. JPEG bytes. */
    public function card(JobPosting $job): string
    {
        $width = 1200;
        $height = 630;
        $pad = 52;
        $imageWidth = 520;

        $canvas = $this->canvas($width, $height);
        $brand = $this->brandColour($job);

        $this->drawHero($canvas, $job, 0, 0, $imageWidth, $height, $brand);

        $x = $imageWidth + $pad;
        $panel = $width - $imageWidth - $pad * 2;

        $y = $this->drawSchoolHeader($canvas, $job, $x, $pad, $panel, 80, 26, 18);

        $y += 30;
        $this->drawHiringBadge($canvas, $x, $y, $brand, 20);

        $y += 66;
        $y = $this->drawWrapped($canvas, $job->title, self::FONT_BOLD, 44, $this->rgb($canvas, [17, 24, 39]), $x, $y, $panel, 3, 1.18);

        $y += 16;
        $this->drawWrapped($canvas, $this->metaLine($job), self::FONT_REGULAR, 22, $this->rgb($canvas, [75, 85, 99]), $x, $y, $panel, 3, 1.35);

        imagefilledrectangle($canvas, $imageWidth, $height - 10, $width, $height, $this->allocate($canvas, $brand));

        return $this->jpeg($canvas);
    }

    /** A filename for the download, made of the job title. */
    public function filename(JobPosting $job): string
    {
        return (Str::slug($job->title) ?: 'vacancy').'-job-post.jpg';
    }

    // -----------------------------------------------------------------------

    private function canvas(int $width, int $height): GdImage
    {
        $canvas = imagecreatetruecolor($width, $height);
        imagefill($canvas, 0, 0, imagecolorallocate($canvas, 255, 255, 255));
        imagealphablending($canvas, true);

        return $canvas;
    }

    /**
     * The job image, cropped to fill the box, or, with none, the school's brand
     * colour, so a vacancy without a photo still makes a clean image.
     *
     * @param  array{0: int, 1: int, 2: int}  $brand
     */
    private function drawHero(GdImage $canvas, JobPosting $job, int $x, int $y, int $width, int $height, array $brand): void
    {
        $image = $this->loadImage($this->uploads->localPath('public', $job->featured_image_path));

        if ($image) {
            $this->drawCover($canvas, $image, $x, $y, $width, $height);
            imagedestroy($image);

            return;
        }

        // A two-tone brand panel.
        imagefilledrectangle($canvas, $x, $y, $x + $width, $y + $height, $this->allocate($canvas, $brand));
        $darker = array_map(fn (int $channel) => (int) max(0, $channel * 0.78), $brand);
        imagefilledpolygon($canvas, [$x, $y + $height, $x + $width, $y + (int) ($height * 0.45), $x + $width, $y + $height], $this->allocate($canvas, $darker));

        $this->drawWrapped($canvas, $job->school->name, self::FONT_BOLD, (int) min(56, $width / 12), $this->rgb($canvas, [255, 255, 255]), $x + 56, $y + (int) ($height * 0.42), $width - 112, 3, 1.2);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $brand
     */
    private function drawHiringBadge(GdImage $canvas, int $x, int $y, array $brand, int $size): void
    {
        $text = "WE'RE HIRING";
        $box = imagettfbbox($size, 0, $this->font(self::FONT_BOLD), $text);
        $textWidth = abs($box[2] - $box[0]);
        $padX = (int) ($size * 0.9);
        $height = (int) ($size * 2.1);

        $this->roundedRectangle($canvas, $x, $y, $x + $textWidth + $padX * 2, $y + $height, (int) ($height / 2), $this->allocate($canvas, $brand));
        imagettftext($canvas, $size, 0, $x + $padX, $y + (int) ($height / 2 + $size / 2) - 1, $this->rgb($canvas, [255, 255, 255]), $this->font(self::FONT_BOLD), $text);
    }

    /**
     * The school's logo, name and address, from its profile. Returns the y below.
     */
    private function drawSchoolHeader(GdImage $canvas, JobPosting $job, int $x, int $y, int $width, int $logoSize, int $nameSize, int $addressSize): int
    {
        $school = $job->school;
        $logo = $this->loadImage($school->logoAbsolutePath());
        $textX = $x;

        if ($logo) {
            $this->drawContain($canvas, $logo, $x, $y, $logoSize, $logoSize);
            imagedestroy($logo);
            $textX = $x + $logoSize + 22;
        }

        $textWidth = $width - ($textX - $x);
        $nameBottom = $this->drawWrapped($canvas, $school->name, self::FONT_BOLD, $nameSize, $this->rgb($canvas, [17, 24, 39]), $textX, $y + 4, $textWidth, 2, 1.2);

        $address = SchoolContact::for($school)->address;

        if (filled($address)) {
            $nameBottom = $this->drawWrapped($canvas, (string) $address, self::FONT_REGULAR, $addressSize, $this->rgb($canvas, [107, 114, 128]), $textX, $nameBottom + 8, $textWidth, 2, 1.3);
        }

        return max($y + $logoSize, $nameBottom);
    }

    private function drawQr(GdImage $canvas, string $url, int $x, int $y, int $size): void
    {
        $png = (new Builder(writer: new PngWriter, data: $url, size: $size, margin: 10))->build()->getString();
        $qr = imagecreatefromstring($png);

        if ($qr) {
            imagecopyresampled($canvas, $qr, $x, $y, 0, 0, $size, $size, imagesx($qr), imagesy($qr));
            imagedestroy($qr);
        }
    }

    /**
     * Draw text wrapped to a width, at most $maxLines lines, ellipsised past
     * that. Returns the y of the bottom of the last line.
     */
    private function drawWrapped(GdImage $canvas, string $text, string $font, int $size, int $colour, int $x, int $y, int $width, int $maxLines, float $lineHeight, bool $breakAnywhere = false): int
    {
        $fontPath = $this->font($font);
        $lines = $this->wrap($text, $fontPath, $size, $width, $breakAnywhere);

        if (count($lines) > $maxLines) {
            $lines = array_slice($lines, 0, $maxLines);
            $last = rtrim($lines[$maxLines - 1]);

            while ($last !== '' && $this->textWidth($last.'…', $fontPath, $size) > $width) {
                $last = mb_substr($last, 0, -1);
            }

            $lines[$maxLines - 1] = rtrim($last).'…';
        }

        $step = (int) round($size * $lineHeight);
        $baseline = $y + $size;

        foreach ($lines as $line) {
            imagettftext($canvas, $size, 0, $x, $baseline, $colour, $fontPath, $line);
            $baseline += $step;
        }

        return $y + $step * max(1, count($lines));
    }

    /**
     * @return list<string>
     */
    private function wrap(string $text, string $font, int $size, int $width, bool $breakAnywhere): array
    {
        $text = trim((string) preg_replace('/\s+/u', ' ', $text));

        if ($text === '') {
            return [];
        }

        $tokens = $breakAnywhere ? preg_split('//u', $text, -1, PREG_SPLIT_NO_EMPTY) : explode(' ', $text);
        $glue = $breakAnywhere ? '' : ' ';
        $lines = [];
        $current = '';

        foreach ($tokens as $token) {
            $candidate = $current === '' ? $token : $current.$glue.$token;

            if ($this->textWidth($candidate, $font, $size) <= $width || $current === '') {
                $current = $candidate;

                continue;
            }

            $lines[] = $current;
            $current = $token;
        }

        if ($current !== '') {
            $lines[] = $current;
        }

        return $lines;
    }

    private function textWidth(string $text, string $font, int $size): int
    {
        $box = imagettfbbox($size, 0, $font, $text);

        return abs($box[2] - $box[0]);
    }

    private function metaLine(JobPosting $job): string
    {
        return collect([
            $job->employment_type?->label(),
            $job->location,
            $job->closes_at ? 'Apply by '.$job->closes_at->format('j M Y') : null,
        ])->filter()->implode('  •  ');
    }

    /** The address without its scheme, which only takes up room on an image. */
    private function displayUrl(JobPosting $job): string
    {
        return (string) preg_replace('#^https?://#', '', $job->publicUrl());
    }

    private function drawCover(GdImage $canvas, GdImage $image, int $x, int $y, int $width, int $height): void
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = max($width / $sourceWidth, $height / $sourceHeight);
        $cropWidth = (int) round($width / $scale);
        $cropHeight = (int) round($height / $scale);

        imagecopyresampled(
            $canvas, $image, $x, $y,
            (int) (($sourceWidth - $cropWidth) / 2), (int) (($sourceHeight - $cropHeight) / 2),
            $width, $height, $cropWidth, $cropHeight,
        );
    }

    private function drawContain(GdImage $canvas, GdImage $image, int $x, int $y, int $width, int $height): void
    {
        $sourceWidth = imagesx($image);
        $sourceHeight = imagesy($image);
        $scale = min($width / $sourceWidth, $height / $sourceHeight);
        $drawWidth = (int) round($sourceWidth * $scale);
        $drawHeight = (int) round($sourceHeight * $scale);

        imagecopyresampled(
            $canvas, $image,
            $x + (int) (($width - $drawWidth) / 2), $y + (int) (($height - $drawHeight) / 2),
            0, 0, $drawWidth, $drawHeight, $sourceWidth, $sourceHeight,
        );
    }

    private function roundedRectangle(GdImage $canvas, int $x1, int $y1, int $x2, int $y2, int $radius, int $colour): void
    {
        imagefilledrectangle($canvas, $x1 + $radius, $y1, $x2 - $radius, $y2, $colour);
        imagefilledrectangle($canvas, $x1, $y1 + $radius, $x2, $y2 - $radius, $colour);

        foreach ([[$x1 + $radius, $y1 + $radius], [$x2 - $radius, $y1 + $radius], [$x1 + $radius, $y2 - $radius], [$x2 - $radius, $y2 - $radius]] as [$cx, $cy]) {
            imagefilledellipse($canvas, $cx, $cy, $radius * 2, $radius * 2, $colour);
        }
    }

    private function loadImage(?string $path): ?GdImage
    {
        if (! $path || ! is_file($path)) {
            return null;
        }

        $image = @imagecreatefromstring((string) file_get_contents($path));

        return $image instanceof GdImage ? $image : null;
    }

    /**
     * The school website's brand colour, or the platform theme's.
     *
     * @return array{0: int, 1: int, 2: int}
     */
    private function brandColour(JobPosting $job): array
    {
        $hex = $job->school->website?->brand_primary_color
            ?: (ThemePreset::PRESETS[Setting::current()->theme_preset]['colors']['600'] ?? '#166fe5');

        $hex = ltrim((string) $hex, '#');

        if (! preg_match('/^[0-9a-fA-F]{6}$/', $hex)) {
            $hex = '166fe5';
        }

        return [hexdec(substr($hex, 0, 2)), hexdec(substr($hex, 2, 2)), hexdec(substr($hex, 4, 2))];
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function allocate(GdImage $canvas, array $rgb): int
    {
        return imagecolorallocate($canvas, $rgb[0], $rgb[1], $rgb[2]);
    }

    /**
     * @param  array{0: int, 1: int, 2: int}  $rgb
     */
    private function rgb(GdImage $canvas, array $rgb): int
    {
        return $this->allocate($canvas, $rgb);
    }

    private function font(string $relative): string
    {
        return base_path($relative);
    }

    private function jpeg(GdImage $canvas): string
    {
        ob_start();
        imageinterlace($canvas, true);
        imagejpeg($canvas, null, 90);
        imagedestroy($canvas);

        return (string) ob_get_clean();
    }
}
