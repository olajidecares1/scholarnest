<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolDashboardMetrics;
use App\Support\SidebarMeta;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

// ---------------------------------------------------------------------------
// Font Awesome actually loads
// ---------------------------------------------------------------------------

test('Font Awesome is bundled, not fetched from a CDN', function () {
    // The brief asked for the loading problem to be fixed rather than the class
    // names changed around it. It was a <link> to cdnjs in four templates and
    // absent from the fifth, so the EduNest Team's pages rendered no icons at
    // all and everyone's icons vanished without that host.
    $bundle = collect(glob(public_path('build/assets/app-*.css')))
        ->sortByDesc(fn (string $path) => filemtime($path))
        ->first();

    expect($bundle)->not->toBeNull();

    $css = file_get_contents($bundle);

    expect($css)->toContain('Font Awesome')
        ->and($css)->toContain('.fa-solid');

    // And the webfont it needs is emitted beside it.
    expect(glob(public_path('build/assets/fa-solid-900-*.woff2')))->not->toBeEmpty();
});

test('no layout still points at the CDN', function () {
    $offenders = [];

    $views = new RecursiveIteratorIterator(new RecursiveDirectoryIterator(resource_path('views')));

    foreach ($views as $file) {
        if ($file->getExtension() !== 'php') {
            continue;
        }

        if (str_contains(file_get_contents($file->getPathname()), 'cdnjs.cloudflare.com/ajax/libs/font-awesome')) {
            $offenders[] = $file->getFilename();
        }
    }

    expect($offenders)->toBe([]);
});

test('every portal renders Font Awesome icons in its sidebar', function () {
    $this->actingAs($this->admin)->get(route('dashboard'))->assertOk()->assertSee('fa-solid fa-gauge', false);

    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    $this->actingAs($teacher, 'staff')->get(route('staff.dashboard', $this->school))->assertOk()->assertSee('fa-solid fa-gauge', false);

    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $this->actingAs($student, 'student')->get(route('student.dashboard', $this->school))->assertOk()->assertSee('fa-solid fa-gauge', false);

    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian->students()->attach($student->id);
    $this->actingAs($guardian, 'guardian')->get(route('guardian.dashboard', $this->school))->assertOk()->assertSee('fa-solid fa-gauge', false);

    auth('guardian')->logout();

    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->actingAs($team)->get(route('super-admin.dashboard'))->assertOk()->assertSee('fa-solid fa-gauge', false);
});

// ---------------------------------------------------------------------------
// The sidebar itself
// ---------------------------------------------------------------------------

test('the sidebar is white and each item is an icon over a heading and a description', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('border-r border-gray-200 bg-white', false)
        ->assertDontSee('bg-[#111a35]', false)
        ->assertSee('<h4 class="truncate text-[13px] font-semibold leading-tight">Students</h4>', false)
        ->assertSee('Admissions and pupil records');
});

test('no sidebar item falls back to the placeholder icon', function () {
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertDontSee(SidebarMeta::FALLBACK_ICON, false);
});

test('hover and active states are solid steps, visible in both modes', function () {
    // The reported problem was that hover vanished in dark mode: a translucent
    // white wash over a near-black panel is about two per cent of luminance.
    // Both modes now move to a solid step of the neutral scale.
    $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

    expect($html)->toContain('hover:bg-gray-100')
        ->and($html)->toContain('dark:hover:bg-gray-700')
        ->and($html)->toContain('bg-primary-50')
        ->and($html)->toContain('dark:bg-primary-900/40');
});

test('the icons take the school\'s own colour', function () {
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'brand_primary_color' => '#8b1a1a']);

    $html = $this->actingAs($this->admin)->get(route('dashboard'))->getContent();

    preg_match_all('/--color-primary-600:\s*([^;]+);/', $html, $m);

    expect($html)->toContain('text-primary-600')
        ->and(trim(end($m[1])))->not->toBe('');
});

// ---------------------------------------------------------------------------
// A dashboard, not a menu
// ---------------------------------------------------------------------------

test('the dashboard no longer duplicates the sidebar', function () {
    // Asserted on the template rather than the response, because the sidebar
    // legitimately links to Transport and Hostel on the very same page - the
    // claim is that the DASHBOARD stopped repeating them, not that the words
    // vanished from the document.
    $dashboard = file_get_contents(resource_path('views/dashboard.blade.php'));

    expect($dashboard)->not->toContain('x-module-card');

    // And what replaced them is on the page.
    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Attendance today')
        ->assertSee('Waiting on you');
});

test('the headline figures are counted from the database', function () {
    Student::factory()->count(3)->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);

    // Inactive pupils are off the roll and must not be counted.
    Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 3', 'is_active' => false]);

    Staff::factory()->count(2)->create(['school_id' => $this->school->id, 'is_active' => true]);
    Guardian::factory()->create(['school_id' => $this->school->id]);

    // Another school's people must not leak into these counts.
    $other = School::factory()->create();
    Student::factory()->count(5)->create(['school_id' => $other->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $metrics = app(SchoolDashboardMetrics::class)->headline($this->school->fresh());

    expect($metrics['students']['value'])->toBe(4)
        ->and($metrics['staff']['value'])->toBe(2)
        ->and($metrics['guardians']['value'])->toBe(1)

        // JSS 1 and JSS 2 have pupils; JSS 3's only pupil is inactive.
        ->and($metrics['classes']['value'])->toBe(2);
});

test('outstanding marks are counted against what the papers actually require', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    $examination = Examination::factory()->create([
        'school_id' => $this->school->id,
        'class_name' => 'JSS 1',
        'session' => $this->school->currentSession(),
    ]);

    $subjects = ExaminationSubject::factory()->count(3)->create(['examination_id' => $examination->id]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subjects->first()->id,
        'student_id' => $student->id,
        'score' => 70,
    ]);

    $results = app(SchoolDashboardMetrics::class)->results($this->school->fresh());

    // One pupil times three subjects is three marks expected; one is in.
    expect($results['expected'])->toBe(3)
        ->and($results['entered'])->toBe(1)
        ->and($results['outstanding'])->toBe(2)
        ->and($results['published'])->toBe(0);
});

test('a school with nothing recorded gets real zeroes, not blanks', function () {
    $metrics = app(SchoolDashboardMetrics::class);

    expect($metrics->results($this->school)['expected'])->toBe(0)
        ->and($metrics->attendance($this->school)['percent'])->toBeNull()
        ->and($metrics->pending($this->school))->toBe([]);

    $this->actingAs($this->admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('No register has been taken today')
        ->assertSee('Nothing needs your attention');
});
