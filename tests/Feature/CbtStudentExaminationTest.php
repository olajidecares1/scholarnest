<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\CbtTestStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestDocumentUpload;
use App\Models\CbtTestQuestion;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;

/**
 * A school with an active Standard subscription, a teacher, and a student.
 *
 * @return array{0: School, 1: Staff, 2: Student}
 */
function cbtSchool(): array
{
    $school = School::factory()->create();

    $plan = Plan::firstOrCreate(
        ['key' => PlanKey::Standard],
        Plan::factory()->make(['key' => PlanKey::Standard])->toArray(),
    );

    Subscription::factory()->create([
        'school_id' => $school->id,
        'plan_id' => $plan->id,
        'status' => SubscriptionStatus::Active,
    ]);

    $teacher = Staff::factory()->create(['school_id' => $school->id, 'role' => StaffRole::Teacher]);
    $student = Student::factory()->create(['school_id' => $school->id]);

    return [$school, $teacher, $student];
}

/**
 * A published test with $count answerable questions, ready to sit.
 */
function publishedTest(School $school, Staff $teacher, int $count = 3, string $className = 'JSS 1'): CbtTest
{
    $test = CbtTest::factory()->create([
        'school_id' => $school->id,
        'staff_id' => $teacher->id,
        'class_name' => $className,
        'status' => CbtTestStatus::Published,
        'duration_minutes' => 30,
    ]);

    CbtTestQuestion::factory()->answerable()->count($count)->create(['cbt_test_id' => $test->id]);

    return $test;
}

beforeEach(function () {
    [$this->school, $this->teacher, $this->student] = cbtSchool();
});

// -----------------------------------------------------------------------------
// Sitting the examination.
// -----------------------------------------------------------------------------

test('a student sees one question at a time with a palette and a clock', function () {
    $test = publishedTest($this->school, $this->teacher);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->assertSee('cbtAttempt(', false)
        ->assertSee('Submit Test')
        ->assertSee('Previous')
        ->assertSee('Not answered');
});

test('the clock is anchored to the server, not the device', function () {
    // A tablet with a wrong date would otherwise show a candidate minutes they
    // do not have. The server sends its own clock for the page to correct by.
    $test = publishedTest($this->school, $this->teacher);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->assertSee('serverNow', false);
});

test('the paper\'s instructions are shown to the candidate', function () {
    $test = publishedTest($this->school, $this->teacher);
    $test->update(['instructions' => 'Answer ALL questions. Use HB pencil only.']);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->assertSee('Use HB pencil only', false);
});

// -----------------------------------------------------------------------------
// Answers survive.
// -----------------------------------------------------------------------------

test('an answer is saved as soon as it is chosen, before any submit', function () {
    $test = publishedTest($this->school, $this->teacher, 2);
    $question = $test->questions()->with('options')->first();
    $option = $question->options->first();

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 2,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $question->id,
            'cbt_test_question_option_id' => $option->id,
        ])
        ->assertOk()
        ->assertJson(['saved' => true]);

    expect($attempt->answers()->count())->toBe(1);
});

test('a saved answer is still there after the page is reloaded', function () {
    $test = publishedTest($this->school, $this->teacher, 2);
    $question = $test->questions()->with('options')->first();
    $option = $question->options->firstWhere('is_correct', true);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 2,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $question->id,
            'cbt_test_question_option_id' => $option->id,
        ])->assertOk();

    // The reload rebuilds the page from what was stored, which is what makes an
    // accidental refresh mid-examination survivable.
    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertOk()
        ->assertSee((string) $option->id, false);
});

test('changing an answer replaces it rather than recording both', function () {
    $test = publishedTest($this->school, $this->teacher, 2);
    $question = $test->questions()->with('options')->first();
    [$first, $second] = [$question->options[0], $question->options[1]];

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 2,
        'expires_at' => now()->addMinutes(30),
    ]);

    foreach ([$first, $second] as $option) {
        $this->actingAs($this->student, 'student')
            ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
                'cbt_test_question_id' => $question->id,
                'cbt_test_question_option_id' => $option->id,
            ])->assertOk();
    }

    expect($attempt->answers()->count())->toBe(1)
        ->and($attempt->answers()->first()->cbt_test_question_option_id)->toBe($second->id);
});

// -----------------------------------------------------------------------------
// Marking.
// -----------------------------------------------------------------------------

test('the score is worked out automatically on submission', function () {
    $test = publishedTest($this->school, $this->teacher, 4);
    $questions = $test->questions()->with('options')->get();

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 4,
        'expires_at' => now()->addMinutes(30),
    ]);

    // Two right, two wrong.
    foreach ($questions as $index => $question) {
        $option = $index < 2
            ? $question->options->firstWhere('is_correct', true)
            : $question->options->firstWhere('is_correct', false);

        $this->actingAs($this->student, 'student')
            ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
                'cbt_test_question_id' => $question->id,
                'cbt_test_question_option_id' => $option->id,
            ])->assertOk();
    }

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.attempts.submit', [$this->school, $attempt]))
        ->assertRedirect();

    expect((float) $attempt->fresh()->score)->toBe(50.0)
        ->and($attempt->fresh()->submitted_at)->not->toBeNull();
});

test('a question worth more marks weighs more in the score', function () {
    $test = publishedTest($this->school, $this->teacher, 2);
    $questions = $test->questions()->with('options')->orderBy('id')->get();

    // Three marks against one: getting only the heavy question right is 75%.
    $questions[0]->update(['marks' => 3]);
    $questions[1]->update(['marks' => 1]);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 2,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $questions[0]->id,
            'cbt_test_question_option_id' => $questions[0]->options->firstWhere('is_correct', true)->id,
        ])->assertOk();

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.attempts.submit', [$this->school, $attempt]))
        ->assertRedirect();

    expect((float) $attempt->fresh()->score)->toBe(75.0);
});

test('an unmarked paper scores exactly as it always did', function () {
    // Marks default to 1, so weighting changes nothing for tests whose papers
    // never stated a mark allocation.
    $test = publishedTest($this->school, $this->teacher, 4);
    $questions = $test->questions()->with('options')->get();

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 4,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $questions[0]->id,
            'cbt_test_question_option_id' => $questions[0]->options->firstWhere('is_correct', true)->id,
        ])->assertOk();

    $this->actingAs($this->student, 'student')
        ->post(route('student.tests.attempts.submit', [$this->school, $attempt]))
        ->assertRedirect();

    expect((float) $attempt->fresh()->score)->toBe(25.0);
});

test('an expired attempt is submitted and marked without the student acting', function () {
    $test = publishedTest($this->school, $this->teacher, 2);
    $question = $test->questions()->with('options')->first();

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 2,
        'expires_at' => now()->subMinute(),
    ]);

    $attempt->answers()->create([
        'cbt_test_question_id' => $question->id,
        'cbt_test_question_option_id' => $question->options->firstWhere('is_correct', true)->id,
        'is_correct' => true,
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertOk();

    $attempt->refresh();

    expect($attempt->submitted_at)->not->toBeNull()
        ->and($attempt->auto_submitted)->toBeTrue()
        ->and((float) $attempt->score)->toBe(50.0);
});

test('an answer arriving after time has expired is refused and the student sent to their result', function () {
    $test = publishedTest($this->school, $this->teacher, 2);
    $question = $test->questions()->with('options')->first();

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $this->student->id,
        'total_questions' => 2,
        'expires_at' => now()->subMinute(),
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $question->id,
            'cbt_test_question_option_id' => $question->options->first()->id,
        ])
        ->assertStatus(409)
        ->assertJson(['expired' => true]);
});

// -----------------------------------------------------------------------------
// One school's examination never reaches another's.
// -----------------------------------------------------------------------------

test('a student cannot open another student\'s attempt', function () {
    $test = publishedTest($this->school, $this->teacher);
    $other = Student::factory()->create(['school_id' => $this->school->id]);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $other->id,
        'total_questions' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$this->school, $attempt]))
        ->assertForbidden();
});

test('a student cannot answer into another student\'s attempt', function () {
    $test = publishedTest($this->school, $this->teacher);
    $other = Student::factory()->create(['school_id' => $this->school->id]);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $other->id,
        'total_questions' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    $this->actingAs($this->student, 'student')
        ->postJson(route('student.tests.attempts.answer', [$this->school, $attempt]), [
            'cbt_test_question_id' => $test->questions()->first()->id,
            'cbt_test_question_option_id' => $test->questions()->with('options')->first()->options->first()->id,
        ])
        ->assertForbidden();
});

test('a student from one school cannot reach another school\'s attempt', function () {
    [$otherSchool, $otherTeacher, $otherStudent] = cbtSchool();

    $test = publishedTest($otherSchool, $otherTeacher);

    $attempt = CbtTestAttempt::factory()->create([
        'cbt_test_id' => $test->id,
        'student_id' => $otherStudent->id,
        'total_questions' => 3,
        'expires_at' => now()->addMinutes(30),
    ]);

    // 404 rather than 403: the address must not confirm that another
    // school's attempt exists.
    $this->actingAs($this->student, 'student')
        ->get(route('student.tests.attempts.show', [$otherSchool, $attempt]))
        ->assertNotFound();
});

test('a teacher cannot open another school\'s test', function () {
    [$otherSchool, $otherTeacher] = cbtSchool();

    $test = publishedTest($otherSchool, $otherTeacher);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.show', [$otherSchool, $test]))
        ->assertNotFound();
});

test('a teacher cannot upload a document into another school\'s test', function () {
    [$otherSchool, $otherTeacher] = cbtSchool();

    $test = publishedTest($otherSchool, $otherTeacher);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.uploads.store', [$otherSchool, $test]), [])
        ->assertNotFound();
});

test('extracted questions stay with the test they were uploaded to', function () {
    [$otherSchool, $otherTeacher] = cbtSchool();

    $mine = publishedTest($this->school, $this->teacher, 2);
    $theirs = publishedTest($otherSchool, $otherTeacher, 2);

    $upload = CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $mine->id,
        'staff_id' => $this->teacher->id,
        'status' => CbtDocumentUploadStatus::Completed,
    ]);

    CbtTestQuestion::factory()->answerable()->create([
        'cbt_test_id' => $mine->id,
        'cbt_test_document_upload_id' => $upload->id,
    ]);

    expect($mine->questions()->count())->toBe(3)
        ->and($theirs->questions()->count())->toBe(2)
        ->and($theirs->questions()->where('cbt_test_document_upload_id', $upload->id)->count())->toBe(0);
});
