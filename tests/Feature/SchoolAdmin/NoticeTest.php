<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\Student;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can send a notice to all students', function () {
    Student::factory()->count(2)->create(['school_id' => $this->school->id, 'is_active' => true]);

    $response = $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'Mid-Term Break',
        'body' => 'School closes on Friday.',
    ]);

    $response->assertRedirect();

    $notice = SchoolNotice::where('title', 'Mid-Term Break')->firstOrFail();
    expect($notice->school_id)->toBe($this->school->id);
    expect($notice->sent_by)->toBe($this->admin->id);
});

test('a school admin can target a notice to one class', function () {
    $response = $this->actingAs($this->admin)->post(route('notices.store'), [
        'title' => 'JSS 1 Excursion',
        'body' => 'Bring your permission slip.',
        'class_name' => 'JSS 1',
    ]);

    $response->assertRedirect();

    $notice = SchoolNotice::where('title', 'JSS 1 Excursion')->firstOrFail();
    expect($notice->class_name)->toBe('JSS 1');
});

test('a school admin only sees notices from their own school', function () {
    SchoolNotice::factory()->create(['school_id' => $this->school->id, 'title' => 'Own Notice']);
    $otherSchool = School::factory()->create();
    SchoolNotice::factory()->create(['school_id' => $otherSchool->id, 'title' => 'Other Notice']);

    $this->actingAs($this->admin)
        ->get(route('notices.index'))
        ->assertSee('Own Notice')
        ->assertDontSee('Other Notice');
});
