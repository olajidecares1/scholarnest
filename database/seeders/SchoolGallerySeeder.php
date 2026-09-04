<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolGalleryImage;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * Twenty-four gallery photographs for a school's public website.
 *
 * The pictures are DRAWN, not photographs. Nothing here can produce a
 * photorealistic image of a school, so rather than pretend, this paints
 * twenty-four distinct, good-looking abstract plates - each captioned and
 * numbered so you can tell at a glance which one you are looking at while
 * testing the rotation and the viewer. Replace them with real photographs
 * through the school admin's website settings whenever you have them.
 *
 * They are deliberately of MIXED SHAPE - landscape, portrait, square and
 * widescreen. Twenty-four images that were all the same shape would prove
 * nothing about the gallery: the tile crops to a square-ish box while the
 * viewer must letterbox without cropping or stretching, and only mixed shapes
 * exercise both.
 *
 * IDEMPOTENT, twice over. Rows are matched on their path before they are
 * written, and every plate is drawn from a seeded RNG keyed to its own index,
 * so re-running produces the same twenty-four files rather than a fresh set of
 * random ones.
 *
 * Each school gets its OWN copies. Deleting a gallery image from the admin
 * removes the file from disk, so schools sharing one set of files would mean
 * one school tidying up its gallery and blanking everybody else's.
 */
class SchoolGallerySeeder extends Seeder
{
    /**
     * @var list<string>
     */
    private const CAPTIONS = [
        'Morning Assembly',
        'The Science Laboratory',
        'Our Library',
        'Inter-House Sports',
        'Computer Studies',
        'Art and Craft',
        'Cultural Day Parade',
        'The School Choir',
        'Football Practice',
        'Graduation Ceremony',
        'A Classroom in Session',
        'The Reading Corner',
        'Mathematics Club',
        'The School Buses',
        'Prize Giving Day',
        'Chemistry Practical',
        'Playground at Break',
        'Debate Competition',
        'The Dining Hall',
        'The Music Room',
        'Basketball Court',
        'The School Garden',
        "Founders' Day Service",
        'Excursion to the Museum',
    ];

    /**
     * Two colours per plate, as [top, bottom] hex pairs.
     *
     * @var list<array{string, string}>
     */
    private const PALETTE = [
        ['#1e3a8a', '#3b82f6'], ['#065f46', '#34d399'], ['#7c2d12', '#f59e0b'],
        ['#4c1d95', '#a78bfa'], ['#831843', '#f472b6'], ['#0c4a6e', '#38bdf8'],
        ['#14532d', '#84cc16'], ['#7f1d1d', '#fb7185'], ['#1e1b4b', '#818cf8'],
        ['#164e63', '#22d3ee'], ['#713f12', '#fbbf24'], ['#3b0764', '#c084fc'],
    ];

    /**
     * Width and height per plate, cycled so the shapes stay mixed.
     *
     * @var list<array{int, int}>
     */
    private const SHAPES = [
        [1400, 933],   // 3:2 landscape
        [1100, 1100],  // square
        [900, 1350],   // 2:3 portrait
        [1400, 788],   // 16:9 widescreen
    ];

    public function run(): void
    {
        // Only schools that actually have a public website. A Basic school has
        // no site for any of this to appear on.
        $schools = School::query()->whereHas('website')->get();

        foreach ($schools as $school) {
            $this->seedGallery($school);
        }

        $this->command?->info(
            "Seeded {$schools->count()} school(s) with ".count(self::CAPTIONS).' gallery images each.'
        );
    }

    private function seedGallery(School $school): void
    {
        foreach (self::CAPTIONS as $index => $caption) {
            $path = "gallery/seed-{$school->id}-".str_pad((string) ($index + 1), 2, '0', STR_PAD_LEFT).'.jpg';

            Storage::disk('public')->put($path, $this->drawPlate($index, $caption));

            SchoolGalleryImage::updateOrCreate(
                ['school_id' => $school->id, 'image_path' => $path],
                ['caption' => $caption, 'sort_order' => $index + 1],
            );
        }
    }

    /**
     * Paint one plate and return it as JPEG data.
     */
    private function drawPlate(int $index, string $caption): string
    {
        // Keyed to the index, so this plate is the same plate every run.
        mt_srand($index * 7919);

        [$width, $height] = self::SHAPES[$index % count(self::SHAPES)];
        [$top, $bottom] = self::PALETTE[$index % count(self::PALETTE)];

        $image = imagecreatetruecolor($width, $height);
        imagealphablending($image, true);

        $this->paintGradient($image, $width, $height, $this->rgb($top), $this->rgb($bottom));
        $this->paintShapes($image, $width, $height);
        $this->paintVignette($image, $width, $height);
        $this->paintNumber($image, $width, $height, $index + 1);
        $this->paintCaption($image, $width, $height, $caption);

        ob_start();
        imagejpeg($image, null, 82);
        $data = (string) ob_get_clean();

        imagedestroy($image);

        return $data;
    }

    /**
     * A diagonal wash from one colour to the other.
     *
     * @param  array{int, int, int}  $from
     * @param  array{int, int, int}  $to
     */
    private function paintGradient(\GdImage $image, int $width, int $height, array $from, array $to): void
    {
        // Painted small and scaled up. Filling the full plate a strip at a time
        // was a few hundred thousand draw calls each and made seeding crawl; a
        // 64px wash resampled up is indistinguishable and effectively instant.
        $swatch = imagecreatetruecolor(64, 64);

        for ($y = 0; $y < 64; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $ratio = ($x + $y) / 126;

                imagesetpixel($swatch, $x, $y,
                    ((int) ($from[0] + ($to[0] - $from[0]) * $ratio) << 16)
                    | ((int) ($from[1] + ($to[1] - $from[1]) * $ratio) << 8)
                    | (int) ($from[2] + ($to[2] - $from[2]) * $ratio)
                );
            }
        }

        imagecopyresampled($image, $swatch, 0, 0, 0, 0, $width, $height, 64, 64);
        imagedestroy($swatch);
    }

    /**
     * Translucent discs and bands, so each plate has a shape of its own rather
     * than being a flat wash.
     */
    private function paintShapes(\GdImage $image, int $width, int $height): void
    {
        $white = imagecolorallocatealpha($image, 255, 255, 255, 108);
        $dark = imagecolorallocatealpha($image, 0, 0, 0, 112);

        foreach (range(1, 5) as $i) {
            $radius = mt_rand((int) ($width * 0.25), (int) ($width * 0.85));

            imagefilledellipse(
                $image,
                mt_rand(0, $width),
                mt_rand(0, $height),
                $radius,
                $radius,
                $i % 2 === 0 ? $white : $dark,
            );
        }

        // A couple of diagonal bands over the top.
        foreach (range(1, 2) as $i) {
            $offset = mt_rand(0, $height);
            $thickness = mt_rand(40, 120);

            imagefilledpolygon($image, [
                0, $offset,
                $width, $offset - (int) ($height * 0.35),
                $width, $offset - (int) ($height * 0.35) + $thickness,
                0, $offset + $thickness,
            ], $white);
        }
    }

    /**
     * Darken the corners, which is most of what makes a flat drawing read as a
     * photograph rather than as a swatch.
     */
    private function paintVignette(\GdImage $image, int $width, int $height): void
    {
        $steps = 28;

        for ($i = 0; $i < $steps; $i++) {
            $shade = imagecolorallocatealpha($image, 0, 0, 0, 118);

            imagerectangle($image, $i * 2, $i * 2, $width - 1 - $i * 2, $height - 1 - $i * 2, $shade);
        }
    }

    /**
     * A large, faint number, so the order is obvious while clicking through
     * next and previous in the viewer.
     */
    private function paintNumber(\GdImage $image, int $width, int $height, int $number): void
    {
        $font = $this->font();
        $ghost = imagecolorallocatealpha($image, 255, 255, 255, 96);
        $label = str_pad((string) $number, 2, '0', STR_PAD_LEFT);

        if ($font === null) {
            imagestring($image, 5, (int) ($width / 2) - 8, (int) ($height / 2) - 8, $label, $ghost);

            return;
        }

        // From the SHORTER side, so a portrait plate does not get a number
        // taller than it is wide.
        $size = (int) (min($width, $height) * 0.36);
        $box = imagettfbbox($size, 0, $font, $label);

        imagettftext(
            $image,
            $size,
            0,
            (int) (($width - ($box[2] - $box[0])) / 2),
            (int) (($height + ($box[1] - $box[7])) / 2),
            $ghost,
            $font,
            $label,
        );
    }

    /**
     * The caption, on a scrim so it stays readable whatever it sits on.
     */
    private function paintCaption(\GdImage $image, int $width, int $height, string $caption): void
    {
        $font = $this->font();
        $white = imagecolorallocate($image, 255, 255, 255);
        $scrim = imagecolorallocatealpha($image, 0, 0, 0, 68);

        if ($font === null) {
            $scrimTop = $height - 56;
            imagefilledrectangle($image, 0, $scrimTop, $width, $height, $scrim);
            imagestring($image, 4, 24, $scrimTop + 18, $caption, $white);

            return;
        }

        // Sized from the WIDTH, and then stepped down until it measurably fits.
        // Sizing it from the height ran "Cultural Day Parade" straight off the
        // edge of the portrait plates, which are the narrow ones.
        //
        // 3.2% is deliberately modest. At 5.5% the caption was shouting across
        // a fifth of the plate, and it is redundant besides - the viewer prints
        // the same caption underneath the photograph as real text. This one is
        // just a label on the plate, so it should read as one.
        $margin = (int) ($width * 0.05);
        $size = (int) ($width * 0.032);

        while ($size > 8) {
            $box = imagettfbbox($size, 0, $font, $caption);

            if (($box[2] - $box[0]) <= $width - ($margin * 2)) {
                break;
            }

            $size--;
        }

        // The scrim is sized off the text rather than the plate, so it is a
        // band under the caption at every shape.
        imagefilledrectangle($image, 0, $height - (int) ($size * 2.2), $width, $height, $scrim);

        imagettftext($image, $size, 0, $margin, $height - (int) ($size * 0.85), $white, $font, $caption);
    }

    /**
     * dompdf ships DejaVu and is a hard dependency of the report cards, so it
     * is here. If that ever stops being true the plates fall back to GD's own
     * bitmap font rather than failing to seed.
     */
    private function font(): ?string
    {
        $path = base_path('vendor/dompdf/dompdf/lib/fonts/DejaVuSans-Bold.ttf');

        return is_file($path) ? $path : null;
    }

    /**
     * @return array{int, int, int}
     */
    private function rgb(string $hex): array
    {
        [$r, $g, $b] = sscanf(ltrim($hex, '#'), '%2x%2x%2x');

        return [(int) $r, (int) $g, (int) $b];
    }
}
