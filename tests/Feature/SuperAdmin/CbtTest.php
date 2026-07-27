<?php

use App\Enums\CbtSubjectCategory;
use App\Enums\UserRole;
use App\Models\AdminRole;
use App\Models\CbtExam;
use App\Models\CbtExamBody;
use App\Models\CbtQuestion;
use App\Models\CbtSubject;
use App\Models\User;

beforeEach(function () {
    $this->superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
});

test('super admin can view the cbt overview page', function () {
    CbtExamBody::factory()->create(['name' => 'WAEC', 'code' => 'WAEC']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cbt.index'))
        ->assertStatus(200)
        ->assertSee('WAEC');
});

test('super admin can create an exam body', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.exam-bodies.store'), [
        'name' => 'West African Examinations Council',
        'code' => 'WAEC',
        'description' => 'Conducts the WASSCE.',
    ]);

    $response->assertRedirect();

    $examBody = CbtExamBody::where('code', 'WAEC')->firstOrFail();
    expect($examBody->name)->toBe('West African Examinations Council');
});

test('exam body codes must be unique', function () {
    CbtExamBody::factory()->create(['code' => 'WAEC']);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.exam-bodies.store'), [
            'name' => 'Duplicate',
            'code' => 'WAEC',
        ])
        ->assertSessionHasErrors('code');
});

test('super admin can update and delete an exam body', function () {
    $examBody = CbtExamBody::factory()->create(['name' => 'Old Name']);

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.cbt.exam-bodies.update', $examBody), [
            'name' => 'New Name',
            'code' => $examBody->code,
        ])
        ->assertRedirect();

    expect($examBody->fresh()->name)->toBe('New Name');

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.cbt.exam-bodies.destroy', $examBody))
        ->assertRedirect();

    expect(CbtExamBody::find($examBody->id))->toBeNull();
});

test('super admin can view an exam body detail page', function () {
    $examBody = CbtExamBody::factory()->create(['name' => 'JAMB']);

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cbt.exam-bodies.show', $examBody))
        ->assertStatus(200)
        ->assertSee('JAMB');
});

test('super admin can create a subject', function () {
    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.subjects.store'), [
        'name' => 'Mathematics',
        'category' => CbtSubjectCategory::Science->value,
    ]);

    $response->assertRedirect();

    $subject = CbtSubject::where('slug', 'mathematics')->firstOrFail();
    expect($subject->name)->toBe('Mathematics');
    expect($subject->category)->toBe(CbtSubjectCategory::Science);
});

test('subject names must be unique', function () {
    CbtSubject::factory()->create(['name' => 'Mathematics', 'slug' => 'mathematics']);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.subjects.store'), [
            'name' => 'Mathematics',
            'category' => CbtSubjectCategory::Science->value,
        ])
        ->assertSessionHasErrors('name');
});

test('super admin can assign subjects to an exam body', function () {
    $examBody = CbtExamBody::factory()->create();
    $subjects = CbtSubject::factory()->count(3)->create();

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.cbt.exam-bodies.subjects.update', $examBody), [
            'subjects' => $subjects->pluck('id')->all(),
        ])
        ->assertRedirect();

    expect($examBody->subjects()->pluck('cbt_subjects.id')->sort()->values()->all())
        ->toBe($subjects->pluck('id')->sort()->values()->all());
});

test('super admin can create an exam for a subject and year', function () {
    $examBody = CbtExamBody::factory()->create();
    $subject = CbtSubject::factory()->create();
    $examBody->subjects()->attach($subject);

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.exams.store', $examBody), [
        'cbt_subject_id' => $subject->id,
        'year' => 2015,
        'duration_minutes' => 60,
        'pass_mark' => 50,
    ]);

    $response->assertRedirect();

    $exam = CbtExam::where('cbt_exam_body_id', $examBody->id)->where('year', 2015)->firstOrFail();
    expect($exam->cbt_subject_id)->toBe($subject->id);
});

test('an exam body cannot have two exams for the same subject and year', function () {
    $examBody = CbtExamBody::factory()->create();
    $subject = CbtSubject::factory()->create();
    CbtExam::factory()->create(['cbt_exam_body_id' => $examBody->id, 'cbt_subject_id' => $subject->id, 'year' => 2020]);

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.exams.store', $examBody), [
            'cbt_subject_id' => $subject->id,
            'year' => 2020,
            'duration_minutes' => 60,
            'pass_mark' => 50,
        ])
        ->assertSessionHasErrors('year');
});

test('super admin can view and delete an exam', function () {
    $exam = CbtExam::factory()->create();

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.cbt.exams.show', $exam))
        ->assertStatus(200);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.cbt.exams.destroy', $exam))
        ->assertRedirect();

    expect(CbtExam::find($exam->id))->toBeNull();
});

test('super admin can add a question with options and a correct answer', function () {
    $exam = CbtExam::factory()->create();

    $response = $this->actingAs($this->superAdmin)->post(route('super-admin.cbt.questions.store', $exam), [
        'question_text' => 'What is 2 + 2?',
        'options' => ['3', '4', '5', '6'],
        'correct_index' => 1,
    ]);

    $response->assertRedirect();

    $question = CbtQuestion::where('cbt_exam_id', $exam->id)->firstOrFail();
    expect($question->question_text)->toBe('What is 2 + 2?');
    expect($question->options)->toHaveCount(4);

    $correct = $question->correctOption();
    expect($correct->label)->toBe('B');
    expect($correct->option_text)->toBe('4');
});

test('a question must have between two and five options', function () {
    $exam = CbtExam::factory()->create();

    $this->actingAs($this->superAdmin)
        ->post(route('super-admin.cbt.questions.store', $exam), [
            'question_text' => 'Invalid question.',
            'options' => ['only one'],
            'correct_index' => 0,
        ])
        ->assertSessionHasErrors('options');
});

test('super admin can update a question and its options', function () {
    $exam = CbtExam::factory()->create();
    $question = CbtQuestion::factory()->create(['cbt_exam_id' => $exam->id]);
    $question->options()->createMany([
        ['label' => 'A', 'option_text' => 'Old A', 'is_correct' => true],
        ['label' => 'B', 'option_text' => 'Old B', 'is_correct' => false],
    ]);

    $this->actingAs($this->superAdmin)
        ->put(route('super-admin.cbt.questions.update', $question), [
            'question_text' => 'Updated question?',
            'options' => ['New A', 'New B', 'New C'],
            'correct_index' => 2,
        ])
        ->assertRedirect();

    $question->refresh();
    expect($question->question_text)->toBe('Updated question?');
    expect($question->options)->toHaveCount(3);
    expect($question->correctOption()->option_text)->toBe('New C');
});

test('super admin can delete a question', function () {
    $exam = CbtExam::factory()->create();
    $question = CbtQuestion::factory()->create(['cbt_exam_id' => $exam->id]);

    $this->actingAs($this->superAdmin)
        ->delete(route('super-admin.cbt.questions.destroy', $question))
        ->assertRedirect();

    expect(CbtQuestion::find($question->id))->toBeNull();
});

test('a team member without manage_cbt permission is forbidden from the cbt module', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.cbt.index'))
        ->assertForbidden();
});

test('a team member with manage_cbt permission can access the cbt module', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_cbt']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.cbt.index'))
        ->assertStatus(200);
});

test('the cbt menu only appears in the sidebar for admins with manage_cbt permission', function () {
    $role = AdminRole::factory()->create(['permissions' => ['manage_payments']]);
    $member = User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'admin_role_id' => $role->id,
    ]);

    $this->actingAs($member)
        ->get(route('super-admin.dashboard'))
        ->assertDontSee('All Exam Bodies');

    $this->actingAs($this->superAdmin)
        ->get(route('super-admin.dashboard'))
        ->assertSee('All Exam Bodies');
});
