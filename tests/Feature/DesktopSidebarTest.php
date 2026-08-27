<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\SidebarIcons;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

// ---------------------------------------------------------------------------
// The rail renders at all
// ---------------------------------------------------------------------------

test('every portal renders its desktop rail', function () {
    // A Blade change that throws is the failure worth catching first.
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('fa-solid fa-gauge-high', false);

    $teacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);
    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fa-solid fa-gauge-high', false);

    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $this->actingAs($student, 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fa-solid fa-gauge-high', false);

    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian->students()->attach($student->id);
    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fa-solid fa-gauge-high', false);
});

test('the rail is white and no longer navy', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('hidden w-28 flex-col border-r border-gray-200 bg-white', false)
        ->assertDontSee('bg-[#111a35]', false);
});

test('labels sit in a small element beneath the icon', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('<small class="block w-full break-words text-[10px] font-semibold leading-[1.3]">Students</small>', false);
});

test('no menu item falls back to the placeholder icon', function () {
    // A menu entry added without an icon renders a plain dot, which is easy to
    // miss in review and obvious here.
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(SidebarIcons::FALLBACK, false);
});

// ---------------------------------------------------------------------------
// The school's own colour
// ---------------------------------------------------------------------------

test('the icons take the school\'s configured colour', function () {
    SchoolWebsite::factory()->create([
        'school_id' => $this->school->id,
        'brand_primary_color' => '#8b1a1a',
    ]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()

        // The icons read --color-primary-600, so the school's own scale being
        // emitted is what makes them its colour.
        ->assertSee('--color-primary-600', false)
        ->assertSee('text-primary-600', false);
});

test('two schools get two different colours', function () {
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'brand_primary_color' => '#8b1a1a']);

    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $otherSchool->id]);
    SchoolWebsite::factory()->create(['school_id' => $otherSchool->id, 'brand_primary_color' => '#12457a']);

    $first = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

    auth()->logout();

    $second = $this->actingAs($otherAdmin)->get(route('dashboard'))->getContent();

    // The LAST declaration, not the first. The platform palette is emitted
    // before the school's override, and in CSS the later one wins - reading
    // the first would report the default and call the override broken.
    $scale = function (string $html): string {
        preg_match_all('/--color-primary-600:\s*([^;]+);/', $html, $m);

        return trim(end($m[1]) ?: '');
    };

    expect($scale($first))->not->toBe('')
        ->and($scale($second))->not->toBe('')
        ->and($scale($first))->not->toBe($scale($second));
});

test('a school with no brand colour still renders', function () {
    // Basic schools have no website record at all, so the fallback is the path
    // most schools take rather than an edge case.
    expect($this->school->website)->toBeNull();

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('--color-primary-600', false);
});

// ---------------------------------------------------------------------------
// The redesign changes nothing about who sees what
// ---------------------------------------------------------------------------

test('a Basic school still gets no premium menu items', function () {
    $basicSchool = activateSchool(School::factory()->create(), PlanKey::Basic);
    $basicAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $basicSchool->id]);

    $this->actingAs($basicAdmin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee('>Website</small>', false)
        ->assertDontSee('>ID Cards</small>', false)
        ->assertDontSee('>CBT Tests</small>', false);
});

test('a Standard school does get them', function () {
    // The other half of the same rule: the gate still lets through what it
    // should, so the test above is not passing because the rail is empty.
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('>Website</small>', false);
});

// ---------------------------------------------------------------------------
// Mobile is untouched
// ---------------------------------------------------------------------------

test('the mobile bar and drawer are exactly as they were', function () {
    // The brief is explicit that tablet and mobile must not change. They live
    // in separate elements below </aside>, and this holds that they still do.
    $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

    expect($html)->toContain('fixed inset-x-0 bottom-0 z-40 border-t border-gray-200 bg-white/95 backdrop-blur print:hidden lg:hidden')
        ->and($html)->toContain('rounded-t-[16px] bg-white pb-[calc(env(safe-area-inset-bottom)+0.5rem)] shadow-2xl print:hidden lg:hidden');
});
