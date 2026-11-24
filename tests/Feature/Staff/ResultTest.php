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
use App\Services\ResultRepository;
use Illuminate\Support\Facades\Route;

beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);
    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $this->teacher->id, 'class_name' => 'JSS 1']);
    $this->examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $this->student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'first_name' => 'Amaka', 'last_name' => 'Obi']);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $this->examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $this->student->id, 'score' => 85]);
});

test('the class teacher can view their class\'s report card', function () {
    $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.results.show', [$this->school, $this->examination, $this->student]))
        ->assertOk()
        ->assertJsonFragment(['has_scores' => true]);
});

test('a teacher who is not the class teacher of that class is forbidden', function () {
    $otherTeacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $otherTeacher->id, 'class_name' => 'JSS 2']);

    $this->actingAs($otherTeacher, 'staff')
        ->getJson(route('staff.results.show', [$this->school, $this->examination, $this->student]))
        ->assertForbidden();
});

test('a teacher with no class teacher assignment is forbidden', function () {
    $unassigned = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);

    $this->actingAs($unassigned, 'staff')
        ->getJson(route('staff.results.show', [$this->school, $this->examination, $this->student]))
        ->assertForbidden();
});

test('a class teacher cannot print or download a report card, by any URL', function () {
    // Not "the buttons are gone". There is no endpoint left behind them, so
    // typing the address by hand reaches nothing. Asserted against the raw
    // paths because route() cannot name a route that does not exist, which
    // is itself the point.
    $base = "/schools/{$this->school->uuid}/staff-portal/results/{$this->examination->uuid}/students/{$this->student->uuid}";

    foreach (["{$base}/print", "{$base}/pdf", "{$base}/download", "{$base}/print?format=pdf"] as $url) {
        $this->actingAs($this->teacher, 'staff')
            ->get($url)
            ->assertNotFound();
    }

    expect(fn () => route('staff.results.print', [$this->school, $this->examination, $this->student]))
        ->toThrow(InvalidArgumentException::class)
        ->and(fn () => route('staff.results.pdf', [$this->school, $this->examination, $this->student]))
        ->toThrow(InvalidArgumentException::class);
});

test('the report card page offers no print or download control', function () {
    $response = $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.results.index', $this->school));

    $response->assertOk()
        ->assertDontSee('Download PDF')
        ->assertDontSee('title="Print"', false)
        ->assertDontSee('title="Download PDF"', false)
        ->assertDontSee('result-print-frame');
});

test('the class teacher sees each pupil\'s admission number on the report card list and in the result', function () {
    reportCardsPage($this)
        ->assertOk()
        ->assertSee($this->student->admission_number);

    $response = $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.results.show', [$this->school, $this->examination, $this->student]));

    $response->assertOk();

    expect($response->json('details_html'))->toContain('Admission No.')
        ->toContain($this->student->admission_number)
        ->and($response->json('report_card_html'))->toContain($this->student->admission_number);
});

test('the class teacher can write the class teacher\'s remark but not the principal\'s remark', function () {
    $this->actingAs($this->teacher, 'staff')
        ->putJson(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), [
            'teacher_remark' => 'Doing great this term.',
            'principal_remark' => 'Should never be set by a teacher.',
        ])
        ->assertOk();

    $report = ExaminationReport::where('examination_id', $this->examination->id)->where('student_id', $this->student->id)->firstOrFail();
    expect($report->teacher_remark)->toBe('Doing great this term.');
    expect($report->principal_remark)->toBeNull();
});

test('the send tab has no route on the teacher portal', function () {
    expect(Route::has('staff.results.send'))->toBeFalse();
});

// ---------------------------------------------------------------------------
// The comment box on each pupil's row
// ---------------------------------------------------------------------------

function reportCardsPage($test)
{
    return $test->actingAs($test->teacher, 'staff')->get(route('staff.results.index', [
        $test->school,
        'class' => 'JSS 1',
        'session' => $test->examination->session,
        'term' => $test->examination->term->value,
    ]));
}

test('each pupil on the Report Cards list has a comment box holding their current comment', function () {
    ExaminationReport::firstOrCreateFor($this->examination, $this->student)->update(['teacher_remark' => 'A steady, careful worker.']);

    reportCardsPage($this)
        ->assertOk()
        ->assertSee("Class Teacher's Comment", false)
        ->assertSee('id="remark-'.$this->student->uuid.'"', false)
        ->assertSee('A steady, careful worker.')
        ->assertSee(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), false);
});

test('the comment saves to the Class Teacher\'s Remark the report card prints', function () {
    $this->actingAs($this->teacher, 'staff')
        ->putJson(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), ['teacher_remark' => '  Amaka reads well beyond her age.  '])
        ->assertOk()
        ->assertJson(['teacher_remark' => 'Amaka reads well beyond her age.', 'needs_repush' => false]);

    $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.results.show', [$this->school, $this->examination, $this->student]))
        ->assertOk()
        ->assertSee('Amaka reads well beyond her age.');
});

test('clearing the comment box removes the comment', function () {
    ExaminationReport::firstOrCreateFor($this->examination, $this->student)->update(['teacher_remark' => 'Old comment']);

    $this->actingAs($this->teacher, 'staff')
        ->putJson(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), ['teacher_remark' => '   '])
        ->assertOk();

    expect(ExaminationReport::where('student_id', $this->student->id)->value('teacher_remark'))->toBeNull();
});

test('a comment longer than 1,000 characters is refused', function () {
    $this->actingAs($this->teacher, 'staff')
        ->putJson(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), ['teacher_remark' => str_repeat('a', 1001)])
        ->assertUnprocessable()
        ->assertJsonValidationErrors('teacher_remark');
});

test('changing the comment on a card already in the Repository says it needs pushing again', function () {
    ExaminationScore::where('student_id', $this->student->id)->update(['test_score' => 30, 'exam_score' => 55, 'score' => 85]);
    app(ResultRepository::class)->publish($this->examination, $this->student, $this->teacher, 'Class Teacher');

    $this->actingAs($this->teacher, 'staff')
        ->putJson(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), ['teacher_remark' => 'A new comment.'])
        ->assertOk()
        ->assertJson(['needs_repush' => true]);
});

test('a teacher who is not the Class Teacher cannot write a pupil\'s comment', function () {
    $other = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    TeacherAssignment::factory()->create(['school_id' => $this->school->id, 'staff_id' => $other->id, 'class_name' => 'JSS 2']);

    $this->actingAs($other, 'staff')
        ->putJson(route('staff.results.remarks', [$this->school, $this->examination, $this->student]), ['teacher_remark' => 'Not mine to write.'])
        ->assertForbidden();

    expect(ExaminationReport::where('student_id', $this->student->id)->value('teacher_remark'))->toBeNull();
});
