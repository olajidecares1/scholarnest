<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\User;
use App\Services\SchoolDashboardMetrics;
use Illuminate\Support\Facades\Schema;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

test('audit entries carry the school they belong to', function () {
    // This is the assertion that was missing. SQLite quotes identifiers with
    // double quotes, and a double-quoted name it cannot resolve to a column it
    // silently treats as a STRING LITERAL - so "school_id" = 45 evaluated as
    // 'school_id' = 45, which is false, which is nought rows and no error. The
    // same query on MySQL threw 1054 and returned a 500. Nothing about the
    // dashboard's behaviour caught that; only the schema does.
    expect(Schema::hasColumn('audit_logs', 'school_id'))->toBeTrue();
});

test('an entry about a school record belongs to that school, whoever acted', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id]);

    // Acting as the ScholarNest Team, who belong to no school themselves.
    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($team);

    $entry = AuditLog::record('password.reset', 'Reset a portal password.', $student);

    // The subject wins over the actor: the entry is about that pupil's school.
    expect($entry->school_id)->toBe($this->school->id);
});

test('the acting user supplies the school when the subject has none', function () {
    $this->actingAs($this->admin);

    $entry = AuditLog::record('settings.updated', 'Changed the school settings.');

    expect($entry->school_id)->toBe($this->school->id);
});

test('every guard is recognised, not just school admins', function () {
    $teacher = Staff::factory()->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    $this->actingAs($teacher, 'staff');

    // Recorded with an actor NAME, which is the path that deliberately skips
    // Auth::user() - the school still has to be found.
    $entry = AuditLog::record('result.pushed', 'Pushed results.', null, $teacher->fullName());

    expect($entry->school_id)->toBe($this->school->id)
        ->and($entry->user_id)->toBeNull();
});

test('platform work belongs to no school and stays out of every school log', function () {
    $team = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($team);

    $entry = AuditLog::record('cbt.exam_body.created', 'Created a CBT exam body.');

    expect($entry->school_id)->toBeNull();

    // And it is not swept into a school's dashboard by a null matching anything.
    $activity = app(SchoolDashboardMetrics::class)->recentActivity($this->school);

    expect($activity->pluck('action'))->not->toContain('cbt.exam_body.created');
});

test('one school never sees another school\'s activity', function () {
    $other = activateSchool(School::factory()->create(), PlanKey::Standard);
    $otherAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $other->id]);

    $this->actingAs($otherAdmin);
    AuditLog::record('settings.updated', 'The other school changed its settings.');

    $this->actingAs($this->admin);
    AuditLog::record('settings.updated', 'This school changed its settings.');

    $activity = app(SchoolDashboardMetrics::class)->recentActivity($this->school->fresh());

    expect($activity)->toHaveCount(1)
        ->and($activity->first()->description)->toBe('This school changed its settings.')
        ->and($activity->first()->school_id)->toBe($this->school->id);
});

test('the dashboard renders its recent activity rather than erroring', function () {
    // The 500 that started this: the dashboard could not be opened at all.
    $this->actingAs($this->admin);

    AuditLog::record('student.created', 'Admitted a new pupil.');

    $this->get(route('dashboard'))
        ->assertOk()
        ->assertSee('Recent activity')
        ->assertSee('Admitted a new pupil.');
});
