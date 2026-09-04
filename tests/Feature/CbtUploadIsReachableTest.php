<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\CbtExamBody;
use App\Models\School;
use App\Models\Staff;
use App\Models\User;

/**
 * The reported failure was not a missing feature - the uploader, the queue, the
 * extractor and the review screen all existed. It was that nothing navigated to
 * them: the team's upload page was linked only from the All Exam Bodies landing
 * page, so anybody working inside a particular exam body could not reach it.
 *
 * These tests hold the WAY IN, which is the part that was broken.
 */
beforeEach(function () {
    $this->team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('the ScholarNest Team sidebar links to the CBT uploader', function () {
    $this->actingAs($this->team)
        ->get(route('super-admin.cbt.index'))
        ->assertOk()
        ->assertSee(route('super-admin.cbt.uploads.index'))
        ->assertSee('Upload CBT Document');
});

test('an exam body page offers an upload, pre-selecting that body', function () {
    // The page in the report: JAMB, with its exams listed and no way to upload.
    $examBody = CbtExamBody::factory()->create(['name' => 'JAMB']);

    $this->actingAs($this->team)
        ->get(route('super-admin.cbt.exam-bodies.show', $examBody))
        ->assertOk()
        ->assertSee('Upload Document')
        ->assertSee(route('super-admin.cbt.uploads.index', ['exam_body' => $examBody->uuid]));
});

test('arriving from an exam body pre-selects it on the upload form', function () {
    $examBody = CbtExamBody::factory()->create(['name' => 'JAMB']);

    // x-select-field applies the selection through Alpine rather than a
    // `selected` attribute, so the initial value is what to assert on.
    $this->actingAs($this->team)
        ->get(route('super-admin.cbt.uploads.index', ['exam_body' => $examBody->uuid]))
        ->assertOk()
        ->assertSee("x-data=\"{ value: '{$examBody->id}' }\"", false);
});

test('the upload page no longer promises AI it does not use', function () {
    // Extraction runs locally. Copy promising AI sends somebody looking for an
    // API key that is not needed and is not configured.
    $this->actingAs($this->team)
        ->get(route('super-admin.cbt.uploads.index'))
        ->assertOk()
        ->assertDontSee('Let AI detect it');
});

test('a Standard-plan teacher is told they can upload a paper', function () {
    $school = activateSchool(School::factory()->create(), PlanKey::Standard);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.cbt.tests.index', $school))
        ->assertOk()
        ->assertSee('Word')
        ->assertSee('PDF');
});

test('a Basic-plan teacher still has no CBT at all', function () {
    // CBT is a premium feature; making the uploader findable must not make it
    // available where the plan does not include it.
    $school = activateSchool(School::factory()->create(), PlanKey::Basic);

    $teacher = Staff::factory()->create([
        'school_id' => $school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.cbt.tests.index', $school))
        ->assertForbidden();
});
