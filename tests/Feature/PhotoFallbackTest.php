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

test('the staff dashboard photograph carries its initials fallback', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Standard);
    $staff = Staff::factory()->create([
        'school_id' => $school->id,
        'first_name' => 'sarah',
        'last_name' => 'Makinde',
        'photo_path' => 'staff/missing.jpg',
    ]);

    $this->actingAs($staff, 'staff')
        ->get(route('staff.dashboard', $school))
        ->assertOk()
        ->assertSee('data-fallback="S"', false);
});

test('the fallback script is installed', function () {
    expect(file_get_contents(resource_path('js/app.js')))->toContain('installPhotoFallback();')
        ->and(file_get_contents(resource_path('js/photo-fallback.js')))->toContain('img.complete && img.naturalWidth === 0');
});
