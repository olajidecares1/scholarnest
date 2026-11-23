<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Models\AcademicLevel;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\SubjectOffering;
use App\Services\DefaultClassSubjects;
use App\Services\ExaminationSubjectSync;

/**
 * Basic (Primary) 4 and 5 take the school's own list, BST and RNV as single
 * subjects and Yoruba and Igbo separately; Basic 1 to 3 and 6 keep theirs.
 */
const PRIMARY_4_5_SUBJECTS = [
    'Mathematics', 'English', 'BST', 'RNV', 'CCA', 'History', 'Phonics', 'Music', 'French',
    'Computer', 'Yoruba', 'Igbo', 'Quantitative Reasoning', 'Verbal Reasoning', 'Handwriting',
];

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['current_session' => '2026/2027']), PlanKey::Basic);
    $this->level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Primary']);
});

function primaryClass(object $test, string $name): SchoolClass
{
    return SchoolClass::factory()->create(['school_id' => $test->school->id, 'academic_level_id' => $test->level->id, 'name' => $name]);
}

/**
 * @return list<string>
 */
function offered(School $school, string $class): array
{
    return DefaultClassSubjects::sortForClass(
        SubjectOffering::with('subject')->where('school_id', $school->id)->where('class_name', $class)->get(),
        $school->id,
        $class,
        fn ($offering) => $offering->subject->name,
    )->map(fn ($offering) => $offering->subject->name)->values()->all();
}

test('Basic 4 and Basic 5 get exactly the list on the school\'s paper, in its order', function (string $class) {
    $schoolClass = primaryClass($this, $class);
    DefaultClassSubjects::ensureForClass($schoolClass);

    expect(DefaultClassSubjects::groupForClass($schoolClass))->toBe('primary_4_5')
        ->and(offered($this->school, $class))->toBe(PRIMARY_4_5_SUBJECTS);
})->with(['Basic 4', 'Basic 5', 'Primary 4', 'Primary 5', 'Basic 4 Gold']);

test('Basic 1 to 3 and 6 keep the primary list', function (string $class) {
    $schoolClass = primaryClass($this, $class);
    DefaultClassSubjects::ensureForClass($schoolClass);

    expect(offered($this->school, $class))->toBe(DefaultClassSubjects::namesForGroup('primary'))
        ->and(offered($this->school, $class))->toContain('Basic Science', 'Social Studies', 'Yoruba / Igbo')
        ->and(offered($this->school, $class))->not->toContain('BST', 'RNV');
})->with(['Basic 1', 'Basic 3', 'Basic 6']);

test('an existing Basic 4 is brought in line, and no entered score is lost', function () {
    // As it stood before: the old primary list.
    config(['subjects.class_groups.primary_4_5.subjects' => DefaultClassSubjects::namesForGroup('primary')]);
    $class = primaryClass($this, 'Basic 4');
    DefaultClassSubjects::ensureForClass($class);
    expect(offered($this->school, 'Basic 4'))->toContain('Basic Science', 'Yoruba / Igbo');

    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Basic 4', 'is_active' => true]);
    $exam = Examination::where('school_id', $this->school->id)->where('class_name', 'Basic 4')->where('session', '2026/2027')->first()
        ?? Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'Basic 4', 'session' => '2026/2027', 'term' => ExamTerm::First->value]);
    ExaminationSubjectSync::syncClass($this->school, 'Basic 4');

    // A Basic Science score was already entered this term.
    $basicScience = $exam->subjects()->where('name', 'Basic Science')->sole();
    ExaminationScore::create(['examination_subject_id' => $basicScience->id, 'student_id' => $student->id, 'score' => 55]);

    // The new list.
    config(['subjects.class_groups.primary_4_5.subjects' => PRIMARY_4_5_SUBJECTS]);
    DefaultClassSubjects::backfillAll();

    expect(offered($this->school, 'Basic 4'))->toBe(PRIMARY_4_5_SUBJECTS);

    $sheet = $exam->subjects()->pluck('name');
    expect($sheet)->toContain('BST', 'RNV', 'Yoruba', 'Igbo')
        // Unscored old subjects come off the score sheet...
        ->and($sheet)->not->toContain('Social Studies', 'Yoruba / Igbo', 'Basic Technology')
        // ...the scored one stays, with its score.
        ->and($sheet)->toContain('Basic Science')
        ->and(ExaminationScore::where('examination_subject_id', $basicScience->id)->value('score'))->toEqual(55);
});

test('running it again adds nothing twice', function () {
    $class = primaryClass($this, 'Basic 5');
    DefaultClassSubjects::ensureForClass($class);

    expect(DefaultClassSubjects::ensureForClass($class))->toBe(0)
        ->and(offered($this->school, 'Basic 5'))->toBe(PRIMARY_4_5_SUBJECTS);
});
