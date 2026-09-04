<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\SchoolFacility;
use App\Models\SchoolWebsite;
use App\Support\FacilityIcon;

/**
 * The facilities row used to print the same fa-school over every entry, so a
 * library, a swimming pool and a sick bay all looked the same and the icons
 * carried no information. Each one now gets an icon that means something.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

test('every icon it can return exists in the bundled Font Awesome', function () {
    // The one that actually matters. A typo in the map renders a blank square
    // on the website and nothing anywhere would fail - the class is simply
    // undefined and the glyph never arrives.
    $css = file_get_contents(base_path('node_modules/@fortawesome/fontawesome-free/css/all.css'));

    expect(FacilityIcon::all())->not->toBeEmpty();

    $missing = array_values(array_filter(
        FacilityIcon::all(),
        fn (string $icon) => ! str_contains($css, ".{$icon} {"),
    ));

    expect($missing)->toBe([]);
});

test('a vague word inside a longer one is not a match', function () {
    // "art" sits inside "Quarters" and "bus" inside "Business". A plain
    // substring search drew a paint palette for the staff quarters.
    expect(FacilityIcon::for('Staff Quarters'))->not->toBe('fa-palette')
        ->and(FacilityIcon::for('Business Centre'))->not->toBe('fa-bus')
        // And the words themselves still match.
        ->and(FacilityIcon::for('Art Room'))->toBe('fa-palette')
        ->and(FacilityIcon::for('Arts Block'))->toBe('fa-palette');
});

test('it reads the words inside a name, not the whole name', function () {
    // Schools write "Main Library", "The Library" and "Junior Library Block".
    expect(FacilityIcon::for('Library'))->toBe('fa-book-open-reader')
        ->and(FacilityIcon::for('Main Library'))->toBe('fa-book-open-reader')
        ->and(FacilityIcon::for('The Junior Library Block'))->toBe('fa-book-open-reader');
});

test('it is not fooled by capitals', function () {
    expect(FacilityIcon::for('SWIMMING POOL'))->toBe('fa-person-swimming')
        ->and(FacilityIcon::for('swimming pool'))->toBe('fa-person-swimming');
});

test('the specific beats the general', function () {
    // "Chemistry Laboratory" contains "laborator" too, and the ordering is the
    // only thing that stops it landing on the generic flask.
    expect(FacilityIcon::for('Chemistry Laboratory'))->toBe('fa-flask-vial')
        ->and(FacilityIcon::for('Physics Laboratory'))->toBe('fa-atom')
        ->and(FacilityIcon::for('Biology Laboratory'))->toBe('fa-dna')
        ->and(FacilityIcon::for('Science Laboratory'))->toBe('fa-flask');
});

test('it recognises what a school actually has', function () {
    expect(FacilityIcon::for('Computer Laboratory'))->toBe('fa-desktop')
        ->and(FacilityIcon::for('Dining Hall'))->toBe('fa-utensils')
        ->and(FacilityIcon::for('Boarding House'))->toBe('fa-bed')
        ->and(FacilityIcon::for('Sick Bay'))->toBe('fa-kit-medical')
        ->and(FacilityIcon::for('School Buses'))->toBe('fa-bus')
        ->and(FacilityIcon::for('The Chapel'))->toBe('fa-place-of-worship')
        ->and(FacilityIcon::for('Music Room'))->toBe('fa-music')
        ->and(FacilityIcon::for('Basketball Court'))->toBe('fa-basketball')
        ->and(FacilityIcon::for('School Garden'))->toBe('fa-seedling')
        ->and(FacilityIcon::for('Playground'))->toBe('fa-child-reaching');
});

test('the category is a second chance when the name says nothing', function () {
    expect(FacilityIcon::for('Block C', 'Laboratory'))->toBe('fa-flask')
        // The name still wins when it does say something.
        ->and(FacilityIcon::for('Music Room', 'Laboratory'))->toBe('fa-music');
});

test('anything unrecognised still gets an icon', function () {
    // A hole in the row would be worse than a generic building.
    expect(FacilityIcon::for('Something Nobody Anticipated'))->toBe('fa-school')
        ->and(FacilityIcon::for(''))->toBe('fa-school')
        ->and(FacilityIcon::for(null))->toBe('fa-school');
});

test('the row sits in its own bordered card', function () {
    SchoolFacility::factory()->create(['school_id' => $this->school->id, 'name' => 'Main Library']);

    $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

    // The section behind it is white as well, so the border is the only thing
    // holding the six together as one group.
    expect($html)->toContain('rounded-[10px] border border-gray-200 bg-white px-5 py-6 shadow-sm')
        ->toContain('grid grid-cols-3 gap-x-4 gap-y-6 sm:grid-cols-6')
        // Six across on a desktop, three on a phone.
        ->toContain('sm:grid-cols-6');
});

test('the panel renders a different icon per facility', function () {
    foreach (['Main Library', 'Swimming Pool', 'Sick Bay', 'Dining Hall'] as $i => $name) {
        SchoolFacility::factory()->create([
            'school_id' => $this->school->id,
            'name' => $name,
            'sort_order' => $i,
        ]);
    }

    $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

    expect($html)->toContain('fa-solid fa-book-open-reader')
        ->toContain('fa-solid fa-person-swimming')
        ->toContain('fa-solid fa-kit-medical')
        ->toContain('fa-solid fa-utensils')
        // Fixed width, so the labels underneath do not sit ragged.
        ->toContain('fa-fw');
});
