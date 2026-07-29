<?php

use App\Enums\UserRole;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtSubject;
use App\Models\School;
use App\Models\User;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
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

test('a school admin cannot access CBT practice management routes', function () {
    $this->actingAs($this->admin)
        ->get(route('super-admin.cbt.index'))
        ->assertForbidden();
});
