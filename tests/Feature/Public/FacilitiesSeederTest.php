<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\SchoolFacility;
use App\Models\SchoolWebsite;
use App\Support\FacilityIcon;
use Database\Seeders\SchoolFacilitiesSeeder;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

test('the panel shows the six from the reference, in its order', function () {
    // The home page shows six and the relation sorts on sort_order, so these
    // six are exactly what a visitor sees.
    $this->seed(SchoolFacilitiesSeeder::class);

    $shown = $this->school->facilities()->take(6)->pluck('name')->all();

    expect($shown)->toBe([
        'Modern Classrooms',
        'Science Laboratories',
        'Computer Laboratory',
        'Library',
        'Sports Facilities',
        'School Hall',
    ]);
});

test('and all six get a different icon', function () {
    $this->seed(SchoolFacilitiesSeeder::class);

    $icons = $this->school->facilities()->take(6)->get()
        ->map(fn (SchoolFacility $f) => FacilityIcon::for($f->name, $f->category));

    // The whole point of the icons. Before them these six were six identical
    // fa-school glyphs, and a Computer Laboratory resolving to the same flask
    // as the Science Laboratories was the bug that followed.
    expect($icons->unique())->toHaveCount(6)
        ->and($icons->all())->toBe([
            'fa-chalkboard-user',
            'fa-flask',
            'fa-desktop',
            'fa-book-open-reader',
            'fa-futbol',
            'fa-building-columns',
        ]);
});

test('they reach the public page', function () {
    $this->seed(SchoolFacilitiesSeeder::class);

    $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

    expect($html)->toContain('Modern Classrooms')
        ->toContain('Computer Laboratory')
        ->toContain('School Hall')
        ->toContain('fa-solid fa-book-open-reader')
        ->not->toContain('Facilities have not been added yet.');
});

test('there are more behind the View all link than the panel shows', function () {
    $this->seed(SchoolFacilitiesSeeder::class);

    expect(SchoolFacility::where('school_id', $this->school->id)->count())->toBe(12);

    // Facilities is the one panel on that row that still has a "View all", so
    // the twelfth has somewhere to be read.
    $this->get(route('public.school-facilities.index', $this->school))
        ->assertOk()
        ->assertSee('The Chapel');
});

test('running it twice does not give a school two libraries', function () {
    $this->seed(SchoolFacilitiesSeeder::class);
    $this->seed(SchoolFacilitiesSeeder::class);

    expect(SchoolFacility::where('school_id', $this->school->id)->count())->toBe(12)
        ->and(SchoolFacility::where('school_id', $this->school->id)->where('name', 'Library')->count())->toBe(1);
});

test('a Basic school gets them too', function () {
    // Facilities is available on every plan, so a Basic school with no public
    // website still has a facilities page of its own to manage. The other
    // website seeders skip these schools; this one must not.
    $basic = activateSchool(School::factory()->create(), PlanKey::Basic);

    $this->seed(SchoolFacilitiesSeeder::class);

    expect(SchoolFacility::where('school_id', $basic->id)->count())->toBe(12);
});
