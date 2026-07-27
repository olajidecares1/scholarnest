<?php

use App\Enums\UserRole;
use App\Models\Report;
use App\Models\User;
use App\Notifications\NewReportNotification;
use Illuminate\Support\Facades\Notification;

test('the report form is publicly accessible', function () {
    $this->get(route('reports.create'))->assertStatus(200);
});

test('a visitor can submit a report anonymously', function () {
    Notification::fake();

    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $response = $this->post(route('reports.store'), [
        'description' => 'Something concerning happened.',
    ]);

    $report = Report::firstOrFail();

    $response->assertRedirect(route('reports.confirmation', ['reference' => $report->reference]));
    expect($report->reporter_name)->toBeNull();
    expect($report->reference)->toStartWith('RPT-');

    Notification::assertSentTo($superAdmin, NewReportNotification::class);
});

test('a report requires a description', function () {
    $this->post(route('reports.store'), [])->assertSessionHasErrors('description');
});
