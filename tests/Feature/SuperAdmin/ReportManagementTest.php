<?php

use App\Enums\ReportStatus;
use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the reports list', function () {
    Report::factory()->create(['reference' => 'RPT-TESTABC1']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.reports.index'))
        ->assertStatus(200)
        ->assertSee('RPT-TESTABC1');
});

test('super admin can view a report and update its status', function () {
    $report = Report::factory()->create(['status' => ReportStatus::New]);

    $response = $this->actingAs($this->superAdmin)->put(route('super-admin.reports.update', $report), [
        'status' => 'resolved',
        'resolution_notes' => 'Investigated and closed.',
    ]);

    $response->assertRedirect();

    $report->refresh();
    expect($report->status)->toBe(ReportStatus::Resolved);
    expect($report->resolution_notes)->toBe('Investigated and closed.');
});
