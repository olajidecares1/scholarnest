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
