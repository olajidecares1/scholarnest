<?php

use App\Enums\IdCardHolderType;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\IdCardTemplate;
use App\Models\School;
use App\Models\User;

/**
 * A School Admin must be able to see what their documents will look like
 * before generating any - and must never be shown another school's branding.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create([
        'name' => 'Marvel Int\' School',
        'current_session' => '2024/2025',
        'contact_address' => '12 Excellence Avenue, GRA, Enugu.',
    ]), PlanKey::Standard);

    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('a school admin can preview the report card template', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('results.template-preview'))
        ->assertOk()
        ->getContent();

    // All six segments, from the real template.
    expect($html)->toContain('Academic Report Card')
        ->and($html)->toContain('Student Name')
        ->and($html)->toContain('Academic Performance')
        ->and($html)->toContain('Grade Key')
        ->and($html)->toContain('Remarks')
        ->and($html)->toContain('Approved')
        ->and($html)->toContain('Marvel Int&#039; School');
});

test('a school admin can preview the ID card template, both faces', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.template-preview'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Front')
        ->and($html)->toContain('Back')
        ->and($html)->toContain('If found, please return to:')
        ->and($html)->toContain('Marvel Int&#039; School');
});

test('the ID card preview uses the school\'s own saved template', function () {
    // Not the platform default: a school that has chosen its colours should
    // see those colours, since that is what its cards will be printed in.
    IdCardTemplate::factory()->create([
        'school_id' => $this->school->id,
        'type' => IdCardHolderType::Student,
        'is_default' => true,
        'secondary_color' => '#004d40',
        'accent_color' => '#f9a825',
    ]);

    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.template-preview'))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('#004d40')
        ->and($html)->toContain('#f9a825');
});

test('the ID card preview covers staff as well as pupils', function () {
    $html = $this->actingAs($this->admin)
        ->get(route('id-cards.template-preview', ['holder' => 'teaching_staff']))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('Staff ID')
        ->and($html)->toContain('Department');
});

test('a school admin never sees another school\'s branding', function () {
    // The school comes from the authenticated user, never from the request,
    // so there is no parameter to tamper with.
    $other = activateSchool(School::factory()->create(['name' => 'Rival Academy']), PlanKey::Standard);

    foreach ([
        route('results.template-preview', ['school' => $other->uuid]),
        route('id-cards.template-preview', ['school' => $other->uuid]),
    ] as $url) {
        $html = $this->actingAs($this->admin)->get($url)->assertOk()->getContent();

        expect($html)->toContain('Marvel Int&#039; School')
            ->and($html)->not->toContain('Rival Academy');
    }
});

test('both previews render the real templates rather than a copy', function () {
    foreach ([
        ['views/school-admin/results/template-preview.blade.php', "@include('school-admin.results._report-card'"],
        ['views/school-admin/id-cards/template-preview.blade.php', 'school-admin.id-cards._card'],
    ] as [$view, $needle]) {
        expect(file_get_contents(resource_path($view)))->toContain($needle);
    }
});

test('the ID card preview is closed to plans that cannot issue cards', function () {
    // ID cards are a Standard and Exclusive feature. Previewing a card a Basic
    // school cannot generate would be an advertisement, not a preview.
    $basic = activateSchool(School::factory()->create(), PlanKey::Basic);
    $basicAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $basic->id]);

    $this->actingAs($basicAdmin)
        ->get(route('id-cards.template-preview'))
        ->assertForbidden();
});

test('the report card preview is open to every plan', function () {
    // Results are core, not a premium extra.
    $basic = activateSchool(School::factory()->create(['name' => 'Basic School']), PlanKey::Basic);
    $basicAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $basic->id]);

    $this->actingAs($basicAdmin)
        ->get(route('results.template-preview'))
        ->assertOk()
        ->assertSee('Basic School');
});

test('both cards carry the school\'s own logo watermark', function () {
    foreach ([
        'views/school-admin/results/_report-card.blade.php',
        'views/school-admin/id-cards/_card.blade.php',
    ] as $view) {
        $markup = file_get_contents(resource_path($view));

        // The school's own crest, behind the content, skipped when there is
        // no logo rather than drawn as an empty box.
        expect($markup)->toContain('$school->logoUrl()')
            ->and($markup)->toContain('opacity: 0.0');
    }
});
