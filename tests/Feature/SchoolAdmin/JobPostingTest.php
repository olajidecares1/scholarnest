<?php

use App\Enums\EmploymentType;
use App\Enums\UserRole;
use App\Models\JobPosting;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can post a job', function () {
    $response = $this->actingAs($this->admin)->post(route('careers.store'), [
        'title' => 'Mathematics Teacher',
        'employment_type' => EmploymentType::FullTime->value,
        'description' => 'Teach JSS and SSS mathematics classes.',
    ]);

    $response->assertRedirect();

    $job = JobPosting::where('title', 'Mathematics Teacher')->firstOrFail();
    expect($job->school_id)->toBe($this->school->id);
    expect($job->is_active)->toBeTrue();
});

test('a school admin only sees job postings from their own school', function () {
    JobPosting::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Job']);
    $otherSchool = School::factory()->create();
    JobPosting::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Job']);

    $this->actingAs($this->admin)
        ->get(route('careers.index'))
        ->assertSee('Own Job')
        ->assertDontSee('Other Job');
});

test('a school admin can update a job posting', function () {
    $job = JobPosting::factory()->create(['school_id' => $this->school->id, 'title' => 'Old Title']);

    $this->actingAs($this->admin)
        ->put(route('careers.update', $job), [
            'title' => 'New Title',
            'employment_type' => EmploymentType::PartTime->value,
            'description' => $job->description,
        ])
        ->assertRedirect();

    expect($job->fresh()->title)->toBe('New Title');
});

test('a school admin can toggle a job posting open and closed', function () {
    $job = JobPosting::factory()->create(['school_id' => $this->school->id, 'is_active' => true]);

    $this->actingAs($this->admin)->post(route('careers.toggle-active', $job));
    expect($job->fresh()->is_active)->toBeFalse();

    $this->actingAs($this->admin)->post(route('careers.toggle-active', $job));
    expect($job->fresh()->is_active)->toBeTrue();
});

test('a school admin cannot modify another school\'s job posting', function () {
    $otherSchool = School::factory()->create();
    $job = JobPosting::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->put(route('careers.update', $job), [
            'title' => 'Hacked',
            'employment_type' => EmploymentType::FullTime->value,
            'description' => 'Hacked',
        ])
        ->assertForbidden();
});

test('a school admin can delete a job posting', function () {
    $job = JobPosting::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('careers.destroy', $job))
        ->assertRedirect();

    expect(JobPosting::find($job->id))->toBeNull();
});
