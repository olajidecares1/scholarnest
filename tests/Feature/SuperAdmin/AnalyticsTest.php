<?php

use App\Enums\UserRole;
use App\Models\PageView;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the analytics page', function () {
    PageView::factory()->count(5)->create(['viewed_at' => now()]);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.analytics.index'))
        ->assertStatus(200)
        ->assertSee('Visitor Timeline');
});

test('analytics totals only count views within the last 30 days', function () {
    PageView::factory()->count(3)->create(['viewed_at' => now()->subDays(5)]);
    PageView::factory()->count(2)->create(['viewed_at' => now()->subDays(45)]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.analytics.index'));

    $response->assertViewHas('totalViews30Days', 3);
});

test('top pages are grouped and labelled by route name, not the opaque url', function () {
    PageView::factory()->count(3)->create([
        'route_name' => 'login',
        'path' => '/'.str_repeat('a', 128),
        'viewed_at' => now(),
    ]);
    PageView::factory()->count(1)->create([
        'route_name' => 'register',
        'path' => '/'.str_repeat('b', 128),
        'viewed_at' => now(),
    ]);

    $response = $this->actingAs($this->superAdmin)->get(route('super-admin.analytics.index'));

    $topPages = $response->viewData('topPages');

    expect($topPages->first()->label())->toBe('Login');
    expect($topPages->first()->views)->toBe(3);
    $response->assertDontSee(str_repeat('a', 128));
});
