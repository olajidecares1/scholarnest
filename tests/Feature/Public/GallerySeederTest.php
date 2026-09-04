<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Models\SchoolWebsite;
use Database\Seeders\SchoolGallerySeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    Storage::fake('public');

    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

test('it seeds twenty-four images with a picture behind every row', function () {
    $this->seed(SchoolGallerySeeder::class);

    $images = SchoolGalleryImage::where('school_id', $this->school->id)->orderBy('sort_order')->get();

    expect($images)->toHaveCount(24);

    // A row pointing at a file that is not there renders a broken image, which
    // is worse than no gallery at all.
    foreach ($images as $image) {
        Storage::disk('public')->assertExists($image->image_path);
        expect($image->caption)->not->toBeEmpty();
    }
});

test('running it twice does not give a school forty-eight', function () {
    $this->seed(SchoolGallerySeeder::class);
    $this->seed(SchoolGallerySeeder::class);

    expect(SchoolGalleryImage::where('school_id', $this->school->id)->count())->toBe(24);
});

test('and produces the same pictures the second time', function () {
    $this->seed(SchoolGallerySeeder::class);

    $path = SchoolGalleryImage::where('school_id', $this->school->id)->orderBy('sort_order')->first()->image_path;
    $first = Storage::disk('public')->get($path);

    $this->seed(SchoolGallerySeeder::class);

    // Each plate is drawn from an RNG seeded on its own index, so re-seeding
    // is not a fresh set of random pictures.
    expect(Storage::disk('public')->get($path))->toBe($first);
});

test('each school gets its own copies, so one tidying up does not blank another', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $other->id, 'is_published' => true]);

    $this->seed(SchoolGallerySeeder::class);

    $mine = SchoolGalleryImage::where('school_id', $this->school->id)->pluck('image_path');
    $theirs = SchoolGalleryImage::where('school_id', $other->id)->pluck('image_path');

    // Deleting a gallery image deletes the file, so a shared path would mean
    // one school's housekeeping emptying everybody else's gallery.
    expect($mine->intersect($theirs))->toBeEmpty()
        ->and($theirs)->toHaveCount(24);
});

test('the shapes are mixed, so the tile and the viewer are both exercised', function () {
    $this->seed(SchoolGallerySeeder::class);

    $shapes = SchoolGalleryImage::where('school_id', $this->school->id)
        ->orderBy('sort_order')
        ->take(4)
        ->get()
        ->map(function (SchoolGalleryImage $image) {
            [$width, $height] = getimagesizefromstring(Storage::disk('public')->get($image->image_path));

            return $width <=> $height;
        });

    // Landscape, square and portrait all present: the tile crops to a box and
    // the viewer must letterbox, and only mixed shapes prove either works.
    expect($shapes->unique()->sort()->values()->all())->toBe([-1, 0, 1]);
});

test('a school with no website is left alone', function () {
    $basic = activateSchool(School::factory()->create(), PlanKey::Basic);

    $this->seed(SchoolGallerySeeder::class);

    expect(SchoolGalleryImage::where('school_id', $basic->id)->count())->toBe(0);
});

test('all twenty-four reach the public page, in eight groups of three', function () {
    Storage::fake('public');
    $this->seed(SchoolGallerySeeder::class);

    $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

    expect(substr_count($html, 'Show photographs'))->toBe(8)
        ->and($html)->toContain('Show photographs 22 to 24')
        // And the viewer can walk to the last one.
        ->toContain('Excursion to the Museum');
});
