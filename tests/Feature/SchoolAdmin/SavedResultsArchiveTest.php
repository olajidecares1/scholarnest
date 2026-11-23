<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use App\Services\ExaminationResultCalculator;
use App\Services\ReportCardData;
use App\Services\SessionResultCalculator;

/**
 * Saved results stay reachable: by session, term and class, and one student's
 * whole record by admission number, with cumulative results fetched from the
 * earlier terms of the session automatically.
 *
 * The case that matters most: pupils moved up a class. Their marks are saved
 * against last session's examinations, and must still be found there.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create([
        'current_session' => '2026/2027',
        'cumulative_results_enabled' => true,
    ]), PlanKey::Basic);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    foreach (['JSS 1', 'JSS 2'] as $order => $name) {
        SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => $name, 'sort_order' => $order]);
    }

    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

/**
 * An examination with two subjects out of 100, and the given marks.
 *
 * @param  array<int, array{0: Student, 1: int|float, 2: int|float}>  $marks  student, maths, english
 */
function savedExam(School $school, string $session, ExamTerm $term, string $class, array $marks): Examination
{
    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'session' => $session,
        'term' => $term->value,
        'class_name' => $class,
    ]);

    // The factory draws a unique default name from a list of five even when
    // a name is given, so reset it rather than run out across many exams.
    fake()->unique(true);

    $maths = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Mathematics', 'max_score' => 100]);
    $english = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'English', 'max_score' => 100]);

    foreach ($marks as [$student, $m, $e]) {
        ExaminationScore::factory()->create(['examination_subject_id' => $maths->id, 'student_id' => $student->id, 'score' => $m]);
        ExaminationScore::factory()->create(['examination_subject_id' => $english->id, 'student_id' => $student->id, 'score' => $e]);
    }

    return $examination;
}

/**
 * Last session in JSS 1, all three terms; now promoted to JSS 2. A new JSS 1
 * pupil has arrived this session with no marks from last session.
 */
function promotedClass(object $test): array
{
    $ada = Student::factory()->create(['school_id' => $test->school->id, 'class_name' => 'JSS 2', 'is_active' => true, 'first_name' => 'Ada', 'last_name' => 'Obi', 'admission_number' => 'BFA/2025/0001']);
    $ben = Student::factory()->create(['school_id' => $test->school->id, 'class_name' => 'JSS 2', 'is_active' => true, 'first_name' => 'Ben', 'last_name' => 'Eze', 'admission_number' => 'BFA/2025/0002']);
    $newcomer = Student::factory()->create(['school_id' => $test->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'first_name' => 'Chi', 'last_name' => 'New', 'admission_number' => 'BFA/2026/0003']);

    $first = savedExam($test->school, '2025/2026', ExamTerm::First, 'JSS 1', [[$ada, 80, 70], [$ben, 50, 60]]);
    $second = savedExam($test->school, '2025/2026', ExamTerm::Second, 'JSS 1', [[$ada, 90, 80], [$ben, 40, 50]]);
    $third = savedExam($test->school, '2025/2026', ExamTerm::Third, 'JSS 1', [[$ada, 70, 60], [$ben, 70, 80]]);

    return compact('ada', 'ben', 'newcomer', 'first', 'second', 'third');
}

test('a past term lists the pupils who sat it, after they have been moved up a class', function () {
    ['ada' => $ada, 'ben' => $ben, 'newcomer' => $newcomer, 'first' => $first] = promotedClass($this);

    $summaries = ExaminationResultCalculator::summariesFor($first);

    expect($summaries->pluck('student.id')->sort()->values()->all())->toBe(collect([$ada->id, $ben->id])->sort()->values()->all())
        ->and($summaries->firstWhere('student.id', $ada->id)['average'])->toBe(75.0)
        ->and($summaries->firstWhere('student.id', $ada->id)['position'])->toBe(1);

    $this->actingAs($this->admin)
        ->get(route('results.index', ['class' => 'JSS 1', 'session' => '2025/2026', 'term' => 'first']))
        ->assertOk()
        ->assertSee('Ada Obi')
        ->assertSee('Ben Eze')
        ->assertDontSee('Chi New');
});

test('the current session still lists today\'s class, with or without marks', function () {
    $ada = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $blank = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $exam = savedExam($this->school, '2026/2027', ExamTerm::First, 'JSS 1', [[$ada, 60, 60]]);

    expect(ExaminationResultCalculator::summariesFor($exam)->pluck('student.id')->sort()->values()->all())
        ->toBe(collect([$ada->id, $blank->id])->sort()->values()->all());
});

test('a newer session than the one set in Settings is treated as current', function () {
    $ada = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $blank = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $exam = savedExam($this->school, '2027/2028', ExamTerm::First, 'JSS 1', [[$ada, 60, 60]]);

    expect(ExaminationResultCalculator::summariesFor($exam)->pluck('student.id'))->toContain($blank->id);
});

test('old sessions and old class names can be chosen on the Results page', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);
    savedExam($this->school, '2019/2020', ExamTerm::Second, 'Basic 7', [[$student, 55, 65]]);

    $this->actingAs($this->admin)
        ->get(route('results.index', ['session' => '2019/2020', 'class' => 'Basic 7', 'term' => 'second']))
        ->assertOk()
        ->assertViewHas('sessionOptions', fn ($options) => in_array('2019/2020', $options, true) && in_array('2026/2027', $options, true))
        ->assertViewHas('classOptions', fn ($options) => array_key_exists('Basic 7', $options) && array_key_exists('JSS 1', $options))
        ->assertSee($student->admission_number);
});

test('the archive lists every session, term and class with saved results', function () {
    promotedClass($this);
    $current = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'is_active' => true]);
    savedExam($this->school, '2026/2027', ExamTerm::First, 'JSS 2', [[$current, 50, 50]]);

    $this->actingAs($this->admin)
        ->get(route('results.archive'))
        ->assertOk()
        ->assertSeeInOrder(['2026/2027 Academic Session', 'First Term', 'JSS 2', '2025/2026 Academic Session', 'First Term', 'JSS 1', 'Second Term', 'Third Term'])
        ->assertViewHas('overview', fn ($overview) => $overview['2025/2026']['third'][0]['students'] === 2
            && $overview['2026/2027']['first'][0]['students'] === 1);
});

test('a student\'s whole record is found by admission number, ignoring case and spaces', function () {
    ['ada' => $ada] = promotedClass($this);

    $this->actingAs($this->admin)
        ->get(route('results.student', ['admission_number' => '  bfa/2025/0001 ']))
        ->assertOk()
        ->assertSee('Ada Obi')
        ->assertSeeInOrder(['2025/2026 Academic Session', 'First Term', 'JSS 1', 'Second Term', 'Third Term'])
        ->assertViewHas('history', fn ($history) => collect($history['2025/2026'])->pluck('average')->all() === [75.0, 85.0, 65.0])
        ->assertSee('Cumulative');
});

test('an unknown admission number, or another school\'s, is not found', function () {
    $other = Student::factory()->create(['admission_number' => 'OTHER/0001']);

    foreach (['NOPE/123', $other->admission_number] as $number) {
        $this->actingAs($this->admin)
            ->get(route('results.student', ['admission_number' => $number]))
            ->assertRedirect(route('results.archive'))
            ->assertSessionHasErrors('admission_number');
    }
});

test('the cumulative fetches every earlier term of the session by admission number, after promotion', function () {
    promotedClass($this);
    $calculator = app(SessionResultCalculator::class);

    // First 75, Second 85, Third 65.
    $whole = $calculator->forAdmissionNumber($this->school, 'BFA/2025/0001', '2025/2026');

    expect($whole['student']->admission_number)->toBe('BFA/2025/0001')
        ->and($whole['className'])->toBe('JSS 1')
        ->and($whole['termsCounted'])->toBe(3)
        ->and($whole['average'])->toBe(75.0)
        ->and($whole['total'])->toBe(450.0)
        ->and($whole['max'])->toBe(600)
        ->and($whole['position'])->toBe(1)
        ->and($whole['numberInClass'])->toBe(2);

    // Up to Second Term: First + Second only.
    $firstTwo = $calculator->forAdmissionNumber($this->school, 'bfa/2025/0001', '2025/2026', ExamTerm::Second);

    expect($firstTwo['termsCounted'])->toBe(2)
        ->and($firstTwo['average'])->toBe(80.0)
        ->and($firstTwo['terms']['third'])->toBeNull()
        ->and(collect($firstTwo['subjects'])->firstWhere('name', 'Mathematics')['average'])->toBe(85.0);

    expect($calculator->forAdmissionNumber($this->school, 'NOPE', '2025/2026'))->toBeNull();
});

test('a term recorded under another arm is still pulled into the cumulative', function () {
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1', 'is_active' => true, 'admission_number' => 'ARM/1']);

    savedExam($this->school, '2026/2027', ExamTerm::First, 'JSS 1 Gold', [[$student, 60, 60]]);
    savedExam($this->school, '2026/2027', ExamTerm::Second, 'JSS 1', [[$student, 80, 80]]);

    $row = app(SessionResultCalculator::class)->forAdmissionNumber($this->school, 'ARM/1', '2026/2027', ExamTerm::Second);

    expect($row['termsCounted'])->toBe(2)
        ->and($row['average'])->toBe(70.0);
});

test('the Session Results page covers a past session after promotion, up to any term', function () {
    promotedClass($this);

    $this->actingAs($this->admin)
        ->get(route('results.session', ['class' => 'JSS 1', 'session' => '2025/2026', 'up_to' => 'second']))
        ->assertOk()
        ->assertSee('Ada Obi')
        ->assertDontSee('Chi New')
        ->assertSee('First Term + Second Term')
        ->assertViewHas('rows', fn ($rows) => $rows->firstWhere('student.admission_number', 'BFA/2025/0001')['average'] === 80.0
            && $rows->firstWhere('student.admission_number', 'BFA/2025/0002')['average'] === 50.0);
});

test('a past report card counts the class as it sat the examination', function () {
    ['ada' => $ada, 'first' => $first] = promotedClass($this);

    $data = ReportCardData::for($first, $ada);

    expect($data['numberInClass'])->toBe(2)
        ->and($data['summary']['position'])->toBe(1);
});
