<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Database\Seeder;
use Illuminate\Support\Facades\Storage;

/**
 * The words for the About section and the band above the footer.
 *
 * Both segments were rendering empty. The About block had no headline, no
 * mission, no vision, no values and no photograph, so it came out as a heading
 * over one fallback sentence; and the band had nothing to say at all. Neither
 * was a layout problem - there was simply nothing in the database.
 *
 * Every field is written ONLY IF IT IS BLANK. A school that has written its own
 * mission statement must not have it replaced by this one, and this seeder is
 * run against live-ish data often enough for that to matter.
 *
 * The About photograph is drawn rather than photographed - see
 * [SchoolGallerySeeder] for the same reasoning at more length. It is a plate to
 * hold the space until a real photograph of the school arrives.
 */
class SchoolWebsiteContentSeeder extends Seeder
{
    public function run(): void
    {
        $websites = SchoolWebsite::with('school')->get();

        foreach ($websites as $website) {
            $this->fill($website);
        }

        $this->command?->info("Filled the About and call-to-action content for {$websites->count()} website(s).");
    }

    private function fill(SchoolWebsite $website): void
    {
        $school = $website->school;

        // array_filter drops anything the school has already written, so
        // update() only ever touches the blanks.
        $defaults = array_filter([
            'about_headline' => 'Building strong minds, character and future leaders.',
            'about_text' => $this->aboutText($school),
            'mission' => 'To provide quality education that empowers students to excel and impact their world positively.',
            'vision' => 'To be a leading institution recognized for academic excellence and character development.',
            'values' => 'Excellence, Integrity, Discipline, Leadership, Innovation and Respect.',

            'cta_title' => 'Give Your Child the Foundation for a Successful Future',
            'cta_subtitle' => 'Applications for the '.$this->session().' academic session are now open.',
            'cta_text' => 'Apply for Admission',
        ], fn (string $value, string $field) => blank($website->{$field}), ARRAY_FILTER_USE_BOTH);

        if (blank($website->about_image_path)) {
            $path = "website/about-{$website->school_id}.jpg";
            Storage::disk('public')->put($path, $this->drawPlate($website->school_id));
            $defaults['about_image_path'] = $path;
        }

        if ($defaults !== []) {
            $website->update($defaults);
        }
    }

    private function aboutText(School $school): string
    {
        return "{$school->name} is committed to providing a nurturing and challenging environment that inspires students to achieve academic excellence and develop strong moral values.";
    }

    /**
     * "2026/2027", rolled over in September when a school year turns.
     */
    private function session(): string
    {
        $year = (int) now()->year + (now()->month >= 9 ? 1 : 0);

        return $year.'/'.($year + 1);
    }

    /**
     * A 4:3 plate for the About column - a soft wash and a vignette, no text.
     *
     * Deliberately quieter than the gallery plates: those are captioned and
     * numbered so they can be told apart while testing the rotation, and this
     * one just has to sit behind a paragraph without shouting.
     */
    private function drawPlate(int $seed): string
    {
        mt_srand($seed * 104_729);

        $width = 1200;
        $height = 900;

        $image = imagecreatetruecolor($width, $height);

        // Drawn small and scaled up, which is both faster and smoother than
        // filling the full plate a strip at a time.
        $swatch = imagecreatetruecolor(64, 64);

        for ($y = 0; $y < 64; $y++) {
            for ($x = 0; $x < 64; $x++) {
                $ratio = ($x + $y) / 126;

                imagesetpixel($swatch, $x, $y,
                    ((int) (30 + 60 * $ratio) << 16)
                    | ((int) (58 + 90 * $ratio) << 8)
                    | (int) (138 + 100 * $ratio)
                );
            }
        }

        imagecopyresampled($image, $swatch, 0, 0, 0, 0, $width, $height, 64, 64);
        imagedestroy($swatch);

        imagealphablending($image, true);

        $white = imagecolorallocatealpha($image, 255, 255, 255, 112);
        $dark = imagecolorallocatealpha($image, 0, 0, 0, 116);

        foreach (range(1, 5) as $i) {
            $radius = mt_rand((int) ($width * 0.3), (int) ($width * 0.8));

            imagefilledellipse($image, mt_rand(0, $width), mt_rand(0, $height), $radius, $radius, $i % 2 === 0 ? $white : $dark);
        }

        for ($i = 0; $i < 28; $i++) {
            imagerectangle($image, $i * 2, $i * 2, $width - 1 - $i * 2, $height - 1 - $i * 2, imagecolorallocatealpha($image, 0, 0, 0, 118));
        }

        ob_start();
        imagejpeg($image, null, 82);
        $data = (string) ob_get_clean();

        imagedestroy($image);

        return $data;
    }
}
