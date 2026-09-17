<?php

use App\Enums\CbtDocumentUploadStatus;
use App\Enums\CbtTestStatus;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Enums\SubscriptionStatus;
use App\Jobs\ProcessCbtTestDocumentUpload;
use App\Models\CbtTest;
use App\Models\CbtTestAttempt;
use App\Models\CbtTestAttemptAnswer;
use App\Models\CbtTestDocumentUpload;
use App\Models\Plan;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;
use App\Models\Subscription;
use App\Services\DocumentExtraction\QuestionExtractionProvider;
use Illuminate\Support\Facades\Bus;
use Illuminate\Support\Facades\Storage;
use PhpOffice\PhpWord\IOFactory;
use PhpOffice\PhpWord\PhpWord;

/**
 * A teacher uploads a past-question compilation holding several years into one
 * test. Nobody sits nine years of JAMB as one examination, so each year becomes
 * a test of its own. These run the real reader over a real Word document.
 */
beforeEach(function () {
    $this->school = School::factory()->create();
    $plan = Plan::firstOrCreate(['key' => PlanKey::Standard], Plan::factory()->make(['key' => PlanKey::Standard])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    $this->teacher = Staff::factory()->create(['school_id' => $this->school->id, 'role' => StaffRole::Teacher]);
    $this->test = CbtTest::factory()->create([
        'school_id' => $this->school->id,
        'staff_id' => $this->teacher->id,
        'title' => 'JAMB Literature Practice',
        'subject' => 'Literature in English',
        'class_name' => 'SSS 3',
        'duration_minutes' => 45,
        'pass_mark' => 60,
    ]);
});

/**
 * A compilation laid out the way the real ones are: a heading per year, each
 * year's questions numbered from 1, the answer after each question.
 *
 * @param  array<int, int>  $questionsPerYear  year => how many questions
 */
function compilationUpload(CbtTest $test, Staff $teacher, array $questionsPerYear): CbtTestDocumentUpload
{
    $word = new PhpWord;
    $section = $word->addSection();
    $section->addText('JAMB LITERATURE-IN-ENGLISH');
    $section->addText('Past Questions and Correct Answers');

    foreach ($questionsPerYear as $year => $count) {
        $section->addText("UTME {$year} — LITERATURE-IN-ENGLISH");

        for ($n = 1; $n <= $count; $n++) {
            $section->addText("{$n}. In the {$year} paper, question {$n} asks who wrote the play?");

            foreach (['A' => 'Soyinka', 'B' => 'Achebe', 'C' => 'Rotimi', 'D' => 'Clark'] as $label => $text) {
                $section->addText("{$label}. {$text} {$year}-{$n}");
            }

            $section->addText('✓ Correct Answer: C');
        }
    }

    $path = 'cbt-test-uploads/documents/'.uniqid().'.docx';
    $absolute = Storage::disk('local')->path($path);

    if (! is_dir(dirname($absolute))) {
        mkdir(dirname($absolute), 0777, true);
    }

    IOFactory::createWriter($word, 'Word2007')->save($absolute);

    return CbtTestDocumentUpload::factory()->create([
        'cbt_test_id' => $test->id,
        'staff_id' => $teacher->id,
        'path' => $path,
        'mime_type' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
        'status' => CbtDocumentUploadStatus::Pending,
    ]);
}

/**
 * Read the document, the way the job does, whatever state it was left in.
 */
function readUpload(CbtTestDocumentUpload $upload): void
{
    // Written straight to the row: the model in hand may already say Pending
    // from before the last reading, and an unchanged attribute is not saved.
    CbtTestDocumentUpload::whereKey($upload->id)->update(['status' => CbtDocumentUploadStatus::Pending]);

    (new ProcessCbtTestDocumentUpload($upload->fresh()))->handle(app(QuestionExtractionProvider::class));

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed);
}

test('a document holding three years becomes three tests, one per year', function () {
    $upload = compilationUpload($this->test, $this->teacher, [2010 => 4, 2011 => 3, 2012 => 5]);

    readUpload($upload);

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed);

    $tests = CbtTest::where('school_id', $this->school->id)->orderBy('id')->get();

    expect($tests)->toHaveCount(3)
        ->and($tests->pluck('title')->all())->toBe([
            'JAMB Literature Practice — 2010',
            'JAMB Literature Practice — 2011',
            'JAMB Literature Practice — 2012',
        ])
        ->and($tests->map(fn (CbtTest $test) => $test->questions()->count())->all())->toBe([4, 3, 5]);

    // The new tests carry the teacher's settings, and wait as drafts.
    foreach ($tests->skip(1) as $yearTest) {
        expect($yearTest->only(['staff_id', 'subject', 'class_name', 'duration_minutes', 'pass_mark']))
            ->toBe($this->test->only(['staff_id', 'subject', 'class_name', 'duration_minutes', 'pass_mark']))
            ->and($yearTest->status)->toBe(CbtTestStatus::Draft);
    }

    // Each year's questions are its own, numbered as that paper numbered them,
    // with the answer taken out of the wording and marked on the option.
    $question = $tests[1]->questions()->with('options')->first();

    expect($question->question_text)->toBe('In the 2011 paper, question 1 asks who wrote the play?')
        ->and($question->question_number)->toBe(1)
        ->and($question->options)->toHaveCount(4)
        ->and($question->options->firstWhere('is_correct', true)->label)->toBe('C');
});

test('reading the document again refills the same year tests', function () {
    $upload = compilationUpload($this->test, $this->teacher, [2010 => 4, 2011 => 3]);

    readUpload($upload);
    readUpload($upload);

    $tests = CbtTest::where('school_id', $this->school->id)->orderBy('id')->get();

    expect($tests)->toHaveCount(2)
        ->and($tests->pluck('title')->all())->toBe(['JAMB Literature Practice — 2010', 'JAMB Literature Practice — 2011'])
        ->and($tests->map(fn (CbtTest $test) => $test->questions()->count())->all())->toBe([4, 3]);
});

test('a document with one year stays in the test it was uploaded to, name unchanged', function () {
    $upload = compilationUpload($this->test, $this->teacher, [2015 => 5]);

    readUpload($upload);

    expect(CbtTest::where('school_id', $this->school->id)->count())->toBe(1)
        ->and($this->test->fresh()->title)->toBe('JAMB Literature Practice')
        ->and($this->test->questions()->count())->toBe(5);
});

test('the upload page lists the year tests and offers to read the document again', function () {
    $upload = compilationUpload($this->test, $this->teacher, [2010 => 2, 2011 => 2]);
    readUpload($upload);

    $this->actingAs($this->teacher, 'staff')
        ->get(route('staff.cbt.tests.uploads.show', [$this->school, $this->test, $upload]))
        ->assertOk()
        ->assertSee('One Test for Each Year')
        ->assertSee('JAMB Literature Practice — 2011')
        ->assertSee('Read This Document Again');
});

test('reading again is refused once a student has answered, and allowed when they have not', function () {
    Bus::fake();

    $upload = compilationUpload($this->test, $this->teacher, [2010 => 2, 2011 => 2]);
    readUpload($upload);

    $yearTest = CbtTest::where('source_upload_id', $upload->id)->firstOrFail();
    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $attempt = CbtTestAttempt::factory()->create(['student_id' => $student->id, 'cbt_test_id' => $yearTest->id]);

    // Opened, nothing answered: nothing to lose, so the paper may be read again.
    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.uploads.retry', [$this->school, $this->test, $upload]))
        ->assertSessionHasNoErrors();

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Pending);

    // Answered: reading again would delete that answer.
    $upload->update(['status' => CbtDocumentUploadStatus::Completed]);
    $question = $yearTest->questions()->with('options')->first();
    CbtTestAttemptAnswer::create([
        'cbt_test_attempt_id' => $attempt->id,
        'cbt_test_question_id' => $question->id,
        'cbt_test_question_option_id' => $question->options->first()->id,
        'is_correct' => false,
    ]);

    $this->actingAs($this->teacher, 'staff')
        ->post(route('staff.cbt.tests.uploads.retry', [$this->school, $this->test, $upload]))
        ->assertSessionHasErrors('upload');

    expect($upload->fresh()->status)->toBe(CbtDocumentUploadStatus::Completed);
});

test('an empty attempt on the old questions is cleared when the document is read again', function () {
    $upload = compilationUpload($this->test, $this->teacher, [2010 => 2, 2011 => 2]);
    readUpload($upload);

    $student = Student::factory()->create(['school_id' => $this->school->id]);
    $attempt = CbtTestAttempt::factory()->create(['student_id' => $student->id, 'cbt_test_id' => $this->test->id]);

    readUpload($upload);

    expect(CbtTestAttempt::find($attempt->id))->toBeNull();
});
