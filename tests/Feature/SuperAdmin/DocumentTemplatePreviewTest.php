<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Support\IdCardDesign;

/**
 * The ScholarNest Team's preview must be the real template, not a drawing of it.
 * The ID card editor learnt that the expensive way: its preview was a
 * hand-built miniature that drifted until what was approved bore no relation
 * to what printed.
 */
beforeEach(function () {
    $this->team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->school = School::factory()->create([
        'name' => 'Marvel Int\' School',
        'current_session' => '2024/2025',
        'contact_address' => '12 Excellence Avenue, GRA, Enugu.',
        'contact_phone' => '+234 812 345 6789',
    ]);
});

test('the team can reach the document templates page', function () {
    $this->actingAs($this->team)
        ->get(route('super-admin.document-templates.index'))
        ->assertOk()
        ->assertSee('Report Card')
        ->assertSee('ID Card');
});

test('the preview renders the real report card, not a mock-up', function () {
    $html = $this->actingAs($this->team)
        ->get(route('super-admin.document-templates.index', ['school' => $this->school->uuid]))
        ->assertOk()
        ->getContent();

    // Furniture that only the real template produces.
    expect($html)->toContain('Academic Report Card')
        ->and($html)->toContain('Academic Performance')
        ->and($html)->toContain('Grade Key')
        ->and($html)->toContain('Remarks')

        // And the specimen's own marks, flowing through the same code a real
        // card uses to compute grades.
        ->and($html)->toContain('English Language')
        ->and($html)->toContain('Chinedu Okafor');
});

test('the preview renders the real ID card, both faces', function () {
    $html = $this->actingAs($this->team)
        ->get(route('super-admin.document-templates.index', ['school' => $this->school->uuid]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Front')
        ->and($html)->toContain('Back')
        ->and($html)->toContain('If found, please return to:')
        ->and($html)->toContain(IdCardDesign::ACCENT);
});

test('the chosen school\'s own details flow through both templates', function () {
    // Nothing is hardcoded: pick a school and its name, address and session
    // appear on the documents.
    $other = School::factory()->create([
        'name' => 'Bright Star Academy',
        'current_session' => '2030/2031',
        'contact_address' => '5 Unity Road, Ikeja, Lagos.',
    ]);

    $html = $this->actingAs($this->team)
        ->get(route('super-admin.document-templates.index', ['school' => $other->uuid]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Bright Star Academy')
        ->and($html)->toContain('5 Unity Road, Ikeja, Lagos.')
        ->and($html)->toContain('2030/2031');
});

test('the ID card preview covers staff as well as pupils', function () {
    $html = $this->actingAs($this->team)
        ->get(route('super-admin.document-templates.index', [
            'school' => $this->school->uuid,
            'holder' => 'teaching_staff',
        ]))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Staff ID')
        ->and($html)->toContain('Department');
});

test('the preview page includes the templates rather than copying them', function () {
    // The guard against the two drifting apart: this page must @include the
    // real partials, never carry its own version of either design.
    $page = file_get_contents(resource_path('views/super-admin/document-templates/index.blade.php'));

    expect($page)->toContain("@include('school-admin.results._report-card'")
        ->and($page)->toContain('school-admin.id-cards._card')
        ->and($page)->toContain('school-admin.id-cards._card_back');
});

test('a school admin cannot reach the team preview', function () {
    $admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($admin)
        ->get(route('super-admin.document-templates.index'))
        ->assertForbidden();
});
