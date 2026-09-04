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
    // paths because route() cannot name a route that does not exist - which
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

test('the report card page offers no print, download or Student ID control', function () {
    $response = $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.results.index', $this->school));

    $response->assertOk()
        ->assertDontSee('Download PDF')
        ->assertDontSee('title="Print"', false)
        ->assertDontSee('title="Download PDF"', false)
        ->assertDontSee('result-print-frame')
        ->assertDontSee('Student ID')
        // The pupil's admission number is their Student ID; it is not printed
        // on this page at all.
        ->assertDontSee($this->student->admission_number);
});

test('the pupil\'s Student ID is withheld from the data, not just the markup', function () {
    // A value the response never carries cannot be read out of the page
    // source, off the network tab, or by replaying the request by hand.
    $response = $this->actingAs($this->teacher, 'staff')
        ->getJson(route('staff.results.show', [$this->school, $this->examination, $this->student]));

    $response->assertOk()
        ->assertJsonMissing(['card_number' => $this->student->admission_number]);

    expect($response->json())->not->toHaveKey('card_number')
        ->and($response->json('details_html'))->not->toContain($this->student->admission_number)
        ->and($response->json('details_html'))->not->toContain('Student ID');
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
