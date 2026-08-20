<?php

use App\Enums\UserRole;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtExamBodyClassGrant;
use App\Models\CbtSubject;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school admin can browse the CBT practice exam bank', function () {
    $examBody = CbtExamBody::factory()->create(['name' => 'WAEC', 'code' => 'WAEC']);

    $this->actingAs($this->admin)
        ->get(route('cbt-practice.index'))
        ->assertStatus(200)
        ->assertSee('WAEC');
});

test('a school admin can view exams for an exam body', function () {
    $examBody = CbtExamBody::factory()->create(['name' => 'WAEC']);
    $subject = CbtSubject::factory()->create(['name' => 'Mathematics']);
    CbtExam::factory()->create(['cbt_exam_body_id' => $examBody->id, 'cbt_subject_id' => $subject->id, 'year' => 2024]);

    $this->actingAs($this->admin)
        ->get(route('cbt-practice.show', $examBody))
        ->assertStatus(200)
        ->assertSee('Mathematics')
        ->assertSee('2024');
});

test('a school admin can grant an exam body to a specific class', function () {
    $examBody = CbtExamBody::factory()->create(['name' => 'WAEC']);

    $response = $this->actingAs($this->admin)->post(route('cbt-practice.grants.store', $examBody), [
        'class_name' => 'Primary 4',
    ]);

    $response->assertRedirect();

    $grant = CbtExamBodyClassGrant::where('cbt_exam_body_id', $examBody->id)->firstOrFail();
    expect($grant->school_id)->toBe($this->school->id);
    expect($grant->class_name)->toBe('Primary 4');
});

test('a school admin can revoke a grant', function () {
    $grant = CbtExamBodyClassGrant::factory()->create(['school_id' => $this->school->id]);

    $this->actingAs($this->admin)
        ->delete(route('cbt-practice.grants.destroy', $grant))
        ->assertRedirect();

    expect(CbtExamBodyClassGrant::find($grant->id))->toBeNull();
});

test('a school admin cannot revoke another school\'s grant', function () {
    $otherSchool = School::factory()->create();
    $grant = CbtExamBodyClassGrant::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('cbt-practice.grants.destroy', $grant))
        ->assertForbidden();

    expect(CbtExamBodyClassGrant::find($grant->id))->not->toBeNull();
});

test('a school admin cannot access CBT practice management routes', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.cbt.index'))
        ->assertForbidden();
});
