<?php

use App\Enums\CumulativeAverageBasis;
use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\GradeBand;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\ExaminationResultCalculator;
use App\Services\PublishedResultData;
use App\Services\ReportCardData;
use App\Services\SessionResultCalculator;

const CUMULATIVE_SESSION = '2025/2026';

function cumulativeSchoolAdmin(PlanKey $plan = PlanKey::Basic): User
{
    $school = School::factory()->create(['current_session' => CUMULATIVE_SESSION]);
    DefaultAcademicStructure::seedFor($school);
    activateSchool($school, $plan);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

/**
 * One examination for the term, with the given marks per subject per student.
 *
 * @param  array<int, array<string, float>>  $marks  student id => [subject => score out of 100]
 */
function termExam(School $school, ExamTerm $term, array $marks, string $class = 'JSS 1'): Examination
{
    $examination = Examination::factory()->create([
        'school_id' => $school->id, 'class_name' => $class, 'session' => CUMULATIVE_SESSION, 'term' => $term,
    ]);

    $subjectNames = collect($marks)->flatMap(fn ($row) => array_keys($row))->unique();
    $subjects = $subjectNames->mapWithKeys(fn ($name) => [$name => ExaminationSubject::create([
        'examination_id' => $examination->id, 'name' => $name, 'max_score' => 100,
    ])]);

    foreach ($marks as $studentId => $row) {
        foreach ($row as $name => $score) {
            ExaminationScore::factory()->create([
                'examination_subject_id' => $subjects[$name]->id, 'student_id' => $studentId,
                'score' => $score, 'test_score' => null, 'exam_score' => null, 'grade_override' => null,
            ]);
        }
    }

    return $examination->fresh();
}

function jssStudent(School $school, string $first): Student
{
    return Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'first_name' => $first, 'is_active' => true]);
}

test('it is off by default, so report cards are unchanged', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $ada = jssStudent($school, 'Ada');
    termExam($school, ExamTerm::First, [$ada->id => ['Mathematics' => 60]]);
    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 80]]);

    expect($school->fresh()->usesCumulativeResults())->toBeFalse()
        ->and(ReportCardData::for($third, $ada)['sessionSummary'])->toBeNull();

    $this->actingAs($admin)->get(route('results.print', [$third, $ada]))
        ->assertOk()
        ->assertDontSee('Cumulative Session Result');
});

test('each school turns it on or off for itself, on every plan', function (PlanKey $plan) {
    $admin = cumulativeSchoolAdmin($plan);
    $other = School::factory()->create();

    $this->actingAs($admin)->put(route('academics.cumulative-results.update'), [
        'cumulative_results_enabled' => '1',
        'cumulative_average_basis' => 'all_terms',
    ])->assertRedirect()->assertSessionHasNoErrors();

    $school = $admin->school->fresh();
    expect($school->usesCumulativeResults())->toBeTrue()
        ->and($school->cumulative_average_basis)->toBe(CumulativeAverageBasis::AllTerms)
        ->and($other->fresh()->usesCumulativeResults())->toBeFalse();

    $this->actingAs($admin)->put(route('academics.cumulative-results.update'), [
        'cumulative_average_basis' => 'all_terms',
    ])->assertRedirect();

    expect($admin->school->fresh()->usesCumulativeResults())->toBeFalse();
})->with([PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive]);

test('the setting appears on the Academics page', function () {
    $admin = cumulativeSchoolAdmin();

    $this->actingAs($admin)->get(route('academics.index'))
        ->assertOk()
        ->assertSee('Cumulative Session Results')
        ->assertSee('Use cumulative session results');
});

test('it adds the three terms together using the term averages and the school\'s grade bands', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $school->update(['cumulative_results_enabled' => true]);

    // The school's own scale, so a default-scale grade would be wrong.
    GradeBand::create(['school_id' => $school->id, 'min_percent' => 75, 'max_percent' => 100, 'letter' => 'A1', 'description' => 'Distinction', 'position' => 0]);
    GradeBand::create(['school_id' => $school->id, 'min_percent' => 0, 'max_percent' => 74, 'letter' => 'C4', 'description' => 'Credit', 'position' => 1]);

    $ada = jssStudent($school, 'Ada');
    $bayo = jssStudent($school, 'Bayo');

    termExam($school, ExamTerm::First, [$ada->id => ['Mathematics' => 70, 'English' => 80], $bayo->id => ['Mathematics' => 50, 'English' => 50]]);
    termExam($school, ExamTerm::Second, [$ada->id => ['Mathematics' => 80, 'English' => 90], $bayo->id => ['Mathematics' => 60, 'English' => 60]]);
    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 90, 'English' => 70], $bayo->id => ['Mathematics' => 70, 'English' => 70]]);

    $summary = ReportCardData::for($third, $ada)['sessionSummary'];

    // Term averages: 75, 85, 80, the same figures each term card prints.
    expect($summary['terms']['first']['average'])->toBe(75.0)
        ->and($summary['terms']['second']['average'])->toBe(85.0)
        ->and($summary['terms']['third']['average'])->toBe(80.0)
        ->and($summary['total'])->toEqual(480)
        ->and($summary['max'])->toBe(600)
        ->and($summary['average'])->toBe(80.0)
        ->and($summary['grade'])->toBe('A1')
        ->and($summary['remark'])->toBe('Distinction')
        ->and($summary['position'])->toBe(1)
        ->and($summary['numberInClass'])->toBe(2);

    $maths = collect($summary['subjects'])->firstWhere('name', 'Mathematics');
    expect($maths['terms'])->toBe(['first' => 70.0, 'second' => 80.0, 'third' => 90.0])
        ->and($maths['total'])->toBe(240.0)
        ->and($maths['average'])->toBe(80.0)
        ->and($maths['grade'])->toBe('A1');

    // Bayo: 50, 60, 70 -> 60, on the school's scale a C4, second in class.
    $bayoSummary = ReportCardData::for($third, $bayo)['sessionSummary'];
    expect($bayoSummary['average'])->toBe(60.0)
        ->and($bayoSummary['grade'])->toBe('C4')
        ->and($bayoSummary['position'])->toBe(2);
});

test('a missing term is averaged by the school\'s chosen basis', function (string $basis, float $expected) {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $school->update(['cumulative_results_enabled' => true, 'cumulative_average_basis' => $basis]);

    // Joined in Second Term: no First Term result.
    $ada = jssStudent($school, 'Ada');
    termExam($school, ExamTerm::Second, [$ada->id => ['Mathematics' => 60]]);
    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 90]]);

    $summary = ReportCardData::for($third, $ada)['sessionSummary'];

    expect($summary['terms']['first'])->toBeNull()
        ->and($summary['termsCounted'])->toBe(2)
        ->and($summary['average'])->toBe($expected);
})->with([
    'terms taken' => ['terms_taken', 75.0],
    'all three terms' => ['all_terms', 50.0],
]);

test('only the Third Term card carries the session result', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $school->update(['cumulative_results_enabled' => true]);
    $ada = jssStudent($school, 'Ada');

    $first = termExam($school, ExamTerm::First, [$ada->id => ['Mathematics' => 60]]);
    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 80]]);

    expect(ReportCardData::for($first, $ada)['sessionSummary'])->toBeNull();

    $this->actingAs($admin)->get(route('results.print', [$third, $ada]))
        ->assertOk()
        ->assertSee('Cumulative Session Result')
        ->assertSee('Session Average');

    $this->actingAs($admin)->get(route('results.pdf', [$third, $ada]))->assertOk();
});

test('turning it on or off changes no term result', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $ada = jssStudent($school, 'Ada');
    $bayo = jssStudent($school, 'Bayo');
    termExam($school, ExamTerm::First, [$ada->id => ['Mathematics' => 40], $bayo->id => ['Mathematics' => 95]]);
    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 90], $bayo->id => ['Mathematics' => 50]]);

    $termFigures = fn () => ExaminationResultCalculator::summariesFor($third->fresh())
        ->map(fn ($row) => [$row['student']->id, $row['average'], $row['position']])->all();
    $cardWithoutSession = fn () => collect(ReportCardData::for($third->fresh(), $ada))->except('sessionSummary')
        ->map(fn ($value) => is_object($value) && method_exists($value, 'toArray') ? $value->toArray() : $value)->all();

    $before = [$termFigures(), $cardWithoutSession()['summary']];

    $school->update(['cumulative_results_enabled' => true]);
    $whileOn = [$termFigures(), $cardWithoutSession()['summary']];

    $school->update(['cumulative_results_enabled' => false]);
    $after = [$termFigures(), $cardWithoutSession()['summary']];

    expect($whileOn)->toEqual($before)->and($after)->toEqual($before);
});

test('the Session Results page ranks the class, and says when the setting is off', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $ada = jssStudent($school, 'Ada');
    termExam($school, ExamTerm::First, [$ada->id => ['Mathematics' => 70]]);
    termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 90]]);

    $this->actingAs($admin)->get(route('results.session', ['class' => 'JSS 1', 'session' => CUMULATIVE_SESSION]))
        ->assertOk()
        ->assertSee('Cumulative session results are off for your school');

    $school->update(['cumulative_results_enabled' => true]);

    $this->actingAs($admin)->get(route('results.session', ['class' => 'JSS 1', 'session' => CUMULATIVE_SESSION]))
        ->assertOk()
        ->assertSee('Ada')
        ->assertSee('80%')
        ->assertSee('1st');

    $this->actingAs($admin)->get(route('results.index'))->assertSee('Session Results');
});

test('a published Third Term card keeps the session result it was published with', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $school->update(['cumulative_results_enabled' => true]);
    $ada = jssStudent($school, 'Ada');
    termExam($school, ExamTerm::First, [$ada->id => ['Mathematics' => 70]]);
    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 90]]);

    $snapshot = app(PublishedResultData::class)->snapshot(ReportCardData::for($third, $ada));

    expect($snapshot['sessionSummary']['average'])->toBe(80.0)
        ->and(json_decode(json_encode($snapshot), true)['sessionSummary'])->toEqual($snapshot['sessionSummary']);
});

test('a school cannot see another school\'s marks in its session result', function () {
    $admin = cumulativeSchoolAdmin();
    $school = $admin->school;
    $school->update(['cumulative_results_enabled' => true]);
    $ada = jssStudent($school, 'Ada');

    $otherSchool = School::factory()->create();
    Examination::factory()->create(['school_id' => $otherSchool->id, 'class_name' => 'JSS 1', 'session' => CUMULATIVE_SESSION, 'term' => ExamTerm::First]);

    $third = termExam($school, ExamTerm::Third, [$ada->id => ['Mathematics' => 90]]);
    $row = app(SessionResultCalculator::class)->forStudent($school, $ada, 'JSS 1', CUMULATIVE_SESSION);

    expect($row['terms']['first'])->toBeNull()->and($row['average'])->toBe(90.0);
});
