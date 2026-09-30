<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Support\SidebarMeta;

/**
 * The mobile and tablet navigation (the bottom bar, the More sheet and the
 * Super Admin's slide-out menu) uses Font Awesome icons throughout, the same
 * icons the desktop sidebar shows for each destination.
 */
test('the school admin bottom bar and More sheet use Font Awesome icons', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school);

    $html = $this->actingAs($admin)->get(route('dashboard'))->assertOk()->getContent();

    $mobile = substr($html, strpos($html, 'Mobile/tablet bottom navigation') ?: strpos($html, 'moreOpen'));

    expect($mobile)->not->toContain('<svg')
        ->and($mobile)->toContain(SidebarMeta::icon('students.index'))
        ->and($mobile)->toContain(SidebarMeta::icon('attendance.index'))
        ->and($mobile)->toContain('fa-solid fa-ellipsis')
        ->and($mobile)->toContain(SidebarMeta::icon('academics.index'));
});

test('the super admin menu uses Font Awesome icons', function () {
    $admin = User::factory()->create(['role' => UserRole::SuperAdmin]);

    $html = $this->actingAs($admin)->get(route('super-admin.dashboard'))->assertOk()->getContent();

    // Everything before the page's own content: the slide-out menu, the
    // desktop sidebar and the header.
    $chrome = substr($html, 0, strpos($html, '<main'));

    expect(substr_count($chrome, '<svg'))->toBe(0)
        ->and($html)->toContain(SidebarMeta::icon('super-admin.legal.index'))
        ->and(SidebarMeta::icon('super-admin.legal.index'))->not->toBe(SidebarMeta::FALLBACK_ICON);
});
