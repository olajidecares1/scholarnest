<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;

/**
 * A photograph that cannot be loaded shows the person's initials instead of
 * the browser's broken-image mark (resources/js/photo-fallback.js).
 */
test('people have initials for when their photograph is missing', function () {
    expect(Staff::factory()->make(['first_name' => 'sarah', 'last_name' => 'Makinde'])->initials())->toBe('SM')
        ->and(Student::factory()->make(['first_name' => 'Ada', 'last_name' => 'Obi'])->initials())->toBe('AO');
});

test('a photograph whose file is missing is not linked at all on the dashboard', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);
    $staff = Staff::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'sarah',
        'last_name' => 'Makinde',
        'photo_path' => 'staff/missing.jpg',
    ]);

    // No file behind the record: initials, and no link to a broken image.
    expect($staff->availablePhotoUrl())->toBeNull();

    $html = $this->actingAs($staff, 'staff')->get(route('staff.dashboard', $school))->assertOk()->getContent();

    expect($html)->not->toContain('/media/staff/');
});

test('a photograph whose file exists is shown', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);
    $staff = Staff::factory()->create(['school_id' => $school->id, 'first_name' => 'Sarah']);
    $staff->photoDisk()->put('staff/present.jpg', 'jpeg');
    $staff->update(['photo_path' => 'staff/present.jpg']);

    expect($staff->fresh()->availablePhotoUrl())->toContain('/media/staff/');

    $this->actingAs($staff, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertSee('data-fallback="S"', false)
        ->assertSee('/media/staff/', false);
});

test('the staff dashboard photograph carries its initials fallback in lists', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);
    $staff = Staff::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'sarah',
        'last_name' => 'Makinde',
        'photo_path' => 'staff/missing.jpg',
    ]);

    // With the file missing the banner shows the initials straight away.
    $this->actingAs($staff, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertSee('>S<', false);
});

test('the fallback script is installed', function () {
    expect(file_get_contents(resource_path('js/app.js')))->toContain('installPhotoFallback();')
        ->and(file_get_contents(resource_path('js/photo-fallback.js')))->toContain('img.complete && img.naturalWidth === 0')
        // Covers protected photographs even on a page without the attribute.
        ->and(file_get_contents(resource_path('js/photo-fallback.js')))->toContain('/\\/media\\/(student|staff|guardian|user)\\//');
});
