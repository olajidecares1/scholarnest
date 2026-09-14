<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Support\PortalNavigation;
use App\Support\SidebarMeta;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
});

function portalPupil(School $school): Student
{
    return Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'JSS 1',
        'is_active' => true,
    ]);
}

/**
 * Every item a menu offers, across all its categories.
 */
function menuRoutes(array $menu): Collection
{
    return collect($menu['categories'])->flatten(1)->pluck('route');
}

// ---------------------------------------------------------------------------
// The cards are gone, replaced by an icon over its name
// ---------------------------------------------------------------------------

test('the portals no longer navigate with large cards', function () {
    foreach (['staff', 'student', 'guardian'] as $portal) {
        expect(file_get_contents(resource_path("views/{$portal}/dashboard.blade.php")))
            ->not->toContain('x-module-card');
    }
});

test('each feature is a Font Awesome icon over its name in a small tag', function () {
    $html = $this->actingAs(portalPupil($this->school), 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->getContent();

    // Icon and name asserted together, so a stray <small> elsewhere on the
    // page cannot satisfy this on its own.
    expect($html)->toMatch('/fa-solid fa-square-poll-vertical.*?<\/i>.*?<small[^>]*>\s*Results\s*<\/small>/s');
});

test('features are grouped under headings rather than run together', function () {
    $this->actingAs(portalPupil($this->school), 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->assertSee('Academic')
        ->assertSee('Communication')
        ->assertSee('Account');
});

// ---------------------------------------------------------------------------
// Bottom navigation
// ---------------------------------------------------------------------------

test('every portal has a fixed bottom bar with Home and More', function () {
    $pupil = portalPupil($this->school);

    $this->actingAs($pupil, 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fixed inset-x-0 bottom-0', false)
        ->assertSee('fa-solid fa-ellipsis', false)
        ->assertSee('>Home</small>', false)
        ->assertSee('>More</small>', false);

    auth('student')->logout();

    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $this->actingAs($teacher, 'staff')
        ->get(route('staff.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fa-solid fa-ellipsis', false)
        ->assertSee('>More</small>', false);

    auth('staff')->logout();

    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian->students()->attach($pupil->id);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fa-solid fa-ellipsis', false)
        ->assertSee('>More</small>', false);
});

test('the bottom bar stays short and hands the rest to More', function () {
    $nav = PortalNavigation::forStudent(portalPupil($this->school));

    // Primary destinations plus More. A bottom bar with a tab per feature is
    // just a menu that happens to be at the bottom.
    expect(count($nav['primary']))->toBeLessThanOrEqual(4);

    // And nothing is lost by keeping it short, the rest is in the sheet.
    $labels = collect($nav['categories'])->flatten(1)->pluck('label');

    expect($labels)->toContain('Settings')
        ->and($labels)->toContain('Profile')
        ->and($labels)->toContain('Help & Support');
});

// ---------------------------------------------------------------------------
// Top bar
// ---------------------------------------------------------------------------

test('notifications and messages sit in the top bar with their own icons', function () {
    $this->actingAs(portalPupil($this->school), 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->assertSee('fa-solid fa-bell', false)
        ->assertSee('fa-solid fa-envelope', false)
        ->assertSee(route('student.messages.index', $this->school), false);
});

test('an unread count is shown only where read state is actually tracked', function () {
    // Pupils have read receipts (school_notice_reads is keyed by student_id),
    // so a number is honest.
    expect(PortalNavigation::unreadMessages(portalPupil($this->school)))->toBeInt();

    // Guardians have none. A badge invented from whatever data was to hand
    // would be worse than no badge.
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);

    expect(PortalNavigation::unreadMessages($guardian))->toBeNull();
});

// ---------------------------------------------------------------------------
// Plan and role gating survives the redesign
// ---------------------------------------------------------------------------

test('a Basic teacher is not offered the premium features', function () {
    $basic = activateSchool(School::factory()->create(), PlanKey::Basic);

    $teacher = Staff::factory()->create(['school_id' => $basic->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $routes = menuRoutes(PortalNavigation::forStaff($teacher->fresh()));

    expect($routes)->not->toContain('staff.cbt.tests.index')
        ->and($routes)->not->toContain('staff.diary.index')
        ->and($routes)->not->toContain('staff.id-card.show')
        ->and($routes)->not->toContain('staff.timetable')

        // What every plan buys is still there, this portal is not premium.
        ->and($routes)->toContain('staff.attendance.index')
        ->and($routes)->toContain('staff.exams.index');
});

test('a Standard teacher is offered them', function () {
    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);

    $routes = menuRoutes(PortalNavigation::forStaff($teacher->fresh()));

    expect($routes)->toContain('staff.cbt.tests.index')
        ->and($routes)->toContain('staff.diary.index')
        ->and($routes)->toContain('staff.timetable');
});

test('a non-teaching staff member is not offered the teaching tools', function () {
    $admin = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Administrator, 'is_active' => true]);

    $routes = menuRoutes(PortalNavigation::forStaff($admin->fresh()));

    expect($routes)->not->toContain('staff.attendance.index')
        ->and($routes)->not->toContain('staff.exams.index')
        ->and($routes)->toContain('staff.profile');
});

test('no menu item points at a route that does not exist', function () {
    $pupil = portalPupil($this->school);
    $teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher, 'is_active' => true]);
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $guardian->students()->attach($pupil->id);

    $menus = [
        PortalNavigation::forStudent($pupil),
        PortalNavigation::forStaff($teacher->fresh()),
        PortalNavigation::forGuardian($guardian->fresh(), $pupil),
    ];

    foreach ($menus as $menu) {
        $items = collect($menu['categories'])->flatten(1)->concat($menu['primary']);

        expect($items)->not->toBeEmpty();

        foreach ($items as $item) {
            expect(Route::has($item['route']))->toBeTrue()
                ->and($item['icon'])->not->toBe(SidebarMeta::FALLBACK_ICON)
                ->and($item['url'])->toStartWith('http');
        }
    }
});

// ---------------------------------------------------------------------------
// The parent's children, and the result-token rule
// ---------------------------------------------------------------------------

test('a parent menu is bound to the child they are looking at', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $first = portalPupil($this->school);
    $second = portalPupil($this->school);
    $guardian->students()->attach([$first->id, $second->id]);

    $resultsUrl = fn (Student $child) => collect(PortalNavigation::forGuardian($guardian, $child)['categories'])
        ->flatten(1)
        ->firstWhere('route', 'guardian.children.results')['url'];

    // One token unlocks one child. A menu that pointed at a sibling is exactly
    // the mistake that rule exists to prevent, so the links must differ.
    expect($resultsUrl($first))->toContain((string) $first->getRouteKey())
        ->and($resultsUrl($second))->toContain((string) $second->getRouteKey())
        ->and($resultsUrl($first))->not->toBe($resultsUrl($second));
});

test('a parent still sees their children and can reach Check Result', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $child = portalPupil($this->school);
    $guardian->students()->attach($child->id);

    $this->actingAs($guardian, 'guardian')
        ->get(route('guardian.dashboard', $this->school))
        ->assertOk()
        ->assertSee('Check Result')
        ->assertSee(route('guardian.children.results', [$this->school, $child]), false);
});

test('a parent is offered nothing belonging to another school', function () {
    $guardian = Guardian::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);
    $ownChild = portalPupil($this->school);
    $guardian->students()->attach($ownChild->id);

    $otherSchool = activateSchool(School::factory()->create(), PlanKey::Standard);
    $strangersChild = portalPupil($otherSchool);

    $urls = collect(PortalNavigation::forGuardian($guardian, $ownChild)['categories'])
        ->flatten(1)
        ->pluck('url')
        ->implode(' ');

    expect($urls)->not->toContain((string) $strangersChild->getRouteKey())
        ->and($urls)->not->toContain($otherSchool->slug);
});

// ---------------------------------------------------------------------------
// The pupil's standing restrictions
// ---------------------------------------------------------------------------

test('a pupil is offered no way to edit their details or change their password', function () {
    foreach (menuRoutes(PortalNavigation::forStudent(portalPupil($this->school))) as $route) {
        expect($route)->not->toContain('password')
            ->and($route)->not->toContain('edit')
            ->and($route)->not->toContain('update');
    }
});

// ---------------------------------------------------------------------------
// Branding and dark mode
// ---------------------------------------------------------------------------

test('the menu takes the school colour and reads in both modes', function () {
    $html = $this->actingAs(portalPupil($this->school), 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('bg-primary-50')
        ->and($html)->toContain('text-primary-600')

        // Every one of those has a dark counterpart, so nothing goes invisible
        // when the panel turns near-black.
        ->and($html)->toContain('dark:bg-primary-900/40')
        ->and($html)->toContain('dark:text-primary-300')

        // The bottom bar included, it is fixed over the page, so losing its
        // background would leave the page scrolling underneath the labels.
        ->and($html)->toContain('dark:bg-gray-900/95');
});

// ---------------------------------------------------------------------------
// Mobile and tablet only, there is no desktop layout behind these three
// ---------------------------------------------------------------------------

test('no portal has a desktop sidebar or the offset that paired with it', function () {
    foreach (['staff', 'student', 'guardian'] as $portal) {
        $layout = file_get_contents(resource_path("views/components/{$portal}-layout.blade.php"));

        expect($layout)->not->toContain('<aside')
            ->and($layout)->not->toContain('lg:pl-64')
            ->and($layout)->not->toContain('edn-sidebar-scroll');
    }
});

test('the bottom bar is present at desktop width, not hidden above lg', function () {
    $nav = file_get_contents(resource_path('views/components/portal-bottom-nav.blade.php'));

    // The bar IS the navigation here. Hiding it above lg, which is what it
    // did while a sidebar existed to take over, would leave a wide browser
    // with no navigation at all.
    expect($nav)->not->toContain('lg:hidden');
});

test('a portal on a wide screen stays an app rather than stretching', function () {
    $html = $this->actingAs(portalPupil($this->school), 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->getContent();

    expect($html)->toContain('mx-auto w-full max-w-3xl')
        ->and($html)->toContain('overflow-x-hidden');
});

test('the School Admin and AkademicNest Team dashboards keep their desktop layout', function () {
    // The brief is explicit that these two are not part of the change. If a
    // later edit sweeps their sidebar away with the portals', this fails.
    foreach (['dashboard-layout', 'super-admin-layout'] as $layout) {
        $markup = file_get_contents(resource_path("views/components/{$layout}.blade.php"));

        expect($markup)->toContain('<aside')
            ->and($markup)->toContain('lg:pl-64');
    }

    $admin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $this->school->id,
    ]);

    $this->actingAs($admin)
        ->get(route('dashboard'))
        ->assertOk()
        ->assertSee('lg:pl-64', false);
});

test('the narrow-screen and landscape rules are actually in the built stylesheet', function () {
    // Tailwind only emits what it finds at build time, and a media query that
    // exists solely in the source file does nothing on a phone. This is the
    // check that catches a change made without rebuilding.
    $bundle = collect(glob(public_path('build/assets/app-*.css')))
        ->sortByDesc(fn (string $path) => filemtime($path))
        ->first();

    $css = file_get_contents($bundle);

    expect($css)->toContain('orientation:landscape')
        ->and($css)->toContain('.edn-portal-main')
        ->and($css)->toContain('.edn-bottom-nav')

        // Three columns on a 320px phone, four once there is 360px.
        ->and($css)->toContain('min-width:360px');
});

test('the phone layout is compact where it used to be tall', function () {
    $html = $this->actingAs(portalPupil($this->school), 'student')
        ->get(route('student.dashboard', $this->school))
        ->assertOk()
        ->getContent();

    // The welcome banner cost roughly a third of a small screen before the
    // first thing a pupil came to do. Compact halves it and grows on a tablet.
    expect($html)->toContain('h-12 w-12 shrink-0 items-center justify-center overflow-hidden rounded-[12px]')
        ->and($html)->not->toContain('sm:h-28 sm:w-28')

        // Tighter rhythm on a phone, the old spacing back on a tablet.
        ->and($html)->toContain('space-y-4 sm:space-y-6');
});

test('the two admin dashboards keep the full-size banner', function () {
    $banner = file_get_contents(resource_path('views/components/welcome-banner.blade.php'));

    // Compact is opt-in, so the shared component still renders the original
    // for the two dashboards that are out of scope for this change.
    expect($banner)->toContain('sm:h-28 sm:w-28');

    foreach (['dashboard', 'super-admin/dashboard'] as $view) {
        expect(file_get_contents(resource_path("views/{$view}.blade.php")))
            ->not->toContain('compact');
    }
});

test('content clears the fixed bar without a hardcoded guess', function () {
    $bundle = collect(glob(public_path('build/assets/app-*.css')))
        ->sortByDesc(fn (string $path) => filemtime($path))
        ->first();

    // The bar's height and the space left for it have to agree, so the space
    // is declared in CSS beside it and includes the handset's home indicator.
    expect(file_get_contents($bundle))->toContain('safe-area-inset-bottom');

    foreach (['staff', 'student', 'guardian'] as $portal) {
        expect(file_get_contents(resource_path("views/components/{$portal}-layout.blade.php")))
            ->toContain('edn-portal-main');
    }
});
