<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\Examination;
use App\Models\ExaminationReport;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Models\TeacherAssignment;
use App\Models\TeacherRemark;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'class_name' => 'JSS 1']);
    $this->examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $this->examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 85]);
});

function saveComment($test, Student $student, string $text)
{
    return $test->actingAs($test->teacher, 'staff')
        ->putJson(route('staff.results.remarks', [$test->school, $test->examination, $student]), ['teacher_remark' => $text]);
}

test('a comment the teacher writes is saved to their list and returned for the page', function () {
    saveComment($this, $this->student, 'A careful, hard-working pupil.')
        ->assertOk()
        ->assertJsonPath('saved_remarks.0.body', 'A careful, hard-working pupil.');

    expect(TeacherRemark::query()->where('staff_id', $this->teacher->id)->pluck('body')->all())
        ->toBe(['A careful, hard-working pupil.']);
});

test('the half-typed drafts the box autosaves on the way are not kept', function () {
    saveComment($this, $this->student, 'A careful');
    saveComment($this, $this->student, 'A careful, hard');
    saveComment($this, $this->student, 'A careful, hard-working pupil.');

    expect(TeacherRemark::query()->where('staff_id', $this->teacher->id)->pluck('body')->all())
        ->toBe(['A careful, hard-working pupil.']);
});

test('a comment still on another pupil\'s card stays on the list when this pupil\'s changes', function () {
    $other = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => $this->student->class_name, 'is_active' => true]);

    saveComment($this, $other, 'Keep it up.');
    saveComment($this, $this->student, 'Keep it up.');
    saveComment($this, $this->student, 'Excellent work this term.');

    expect(TeacherRemark::query()->where('staff_id', $this->teacher->id)->pluck('body')->sort()->values()->all())
        ->toBe(['Excellent work this term.', 'Keep it up.']);
});

test('saving the same comment twice, spaced or cased differently, keeps one entry', function () {
    $other = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => $this->student->class_name, 'is_active' => true]);

    saveComment($this, $this->student, 'Good work.');
    saveComment($this, $other, '  good   work. ');

    expect(TeacherRemark::query()->where('staff_id', $this->teacher->id)->count())->toBe(1);
});

test('the Report Cards page lists the saved comments, including ones written before the list existed', function () {
    ExaminationReport::firstOrCreateFor($this->examination, $this->student)->update(['teacher_remark' => 'A joy to teach.']);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.results.index', [$this->school, 'class' => $this->student->class_name, 'session' => $this->examination->session, 'term' => $this->examination->term->value]))
        ->assertOk()
        ->assertSee('id="saved-remarks-data"', false)
        ->assertSee('A joy to teach.')
        ->assertSee('Saved comments');
});

test('a teacher can take a comment off their list, and the card keeps it', function () {
    saveComment($this, $this->student, 'Neat handwriting.');
    $remark = TeacherRemark::query()->where('staff_id', $this->teacher->id)->firstOrFail();

    $this->actingAs($this->teacher, 'staff')
        ->deleteJson(route('staff.results.saved-remarks.destroy', [$this->school, $remark]))
        ->assertOk()
        ->assertJsonPath('saved_remarks', []);

    expect(ExaminationReport::query()->where('student_id', $this->student->id)->value('teacher_remark'))->toBe('Neat handwriting.');
});

test('one teacher\'s saved comments are theirs alone', function () {
    saveComment($this, $this->student, 'Neat handwriting.');
    $remark = TeacherRemark::query()->where('staff_id', $this->teacher->id)->firstOrFail();

    $colleague = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $colleague->id, 'class_name' => 'JSS 2']);

    $this->actingAs($colleague, 'staff')
        ->deleteJson(route('staff.results.saved-remarks.destroy', [$this->school, $remark]))
        ->assertNotFound();

    expect(TeacherRemark::listFor($colleague))->toBeEmpty()
        ->and($remark->fresh())->not->toBeNull();
});
