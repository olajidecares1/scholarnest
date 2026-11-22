<?php

use App\Enums\ClassStream;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\DefaultSubjects;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

const KG_SUBJECTS = [
    'Numeracy', 'Literacy', 'Pre-Science', 'Protective Measures', 'Safety Measures',
    'Health Habits', 'Social Habits', 'Bible Story / CRS', 'Moral Instruction', 'Handwriting',
];

const JSS1_SUBJECTS = [
    'English Language', 'Mathematics', 'Intermediate Science', 'Social and Citizenship Studies',
    'Nigerian History', 'Business Studies', 'CRS', 'PHE', 'Diction', 'French', 'Music',
    'Fashion Design', 'Digital Technology', 'Yoruba',
];

const JSS2_3_SUBJECTS = [
    'English Language', 'Mathematics', 'BST 1 (PHE & ICT)', 'BST 2 (Basic Science & Technology)',
    'Business Studies', 'RNV (Social Studies, CRS & Civic Education)', 'Music', 'French', 'Yoruba',
    'PVS (Agriculture & Home Economics)', 'Phonics',
];

const NURSERY1_SUBJECTS = [
    'Number Work', 'Letter Work', 'Moral Instruction', 'Science', 'Food and Nutrition',
    'Social Habits', 'Health Habits', 'CRS', 'Writing', 'Creative Arts',
];

const NURSERY2_SUBJECTS = [
    'Number Work', 'Letter Work', 'BST', 'Social Habits', 'Health Habits', 'CRS',
    'Food and Nutrition', 'Quantitative Reasoning', 'Verbal Reasoning', 'Civic Education',
    'Agricultural Science', 'Writing',
];

const PRIMARY_SUBJECTS = [
    'Mathematics', 'English', 'History', 'CCA', 'French', 'Quantitative Reasoning',
    'Verbal Reasoning', 'RNV', 'BST', 'Phonics', 'Yoruba / Igbo', 'Handwriting', 'Computer', 'Music',
];

const SS_SUBJECTS = [
    'English Language', 'Mathematics', 'Biology', 'Civic Education', 'Economics',
    'Digital Technology', 'Diction', 'Agricultural Science', 'Geography', 'Food and Nutrition',
];

const SS_SCIENCE = ['Physics', 'Chemistry', 'Further Mathematics'];
const SS_ART = ['CRS', 'Government', 'Literature in English'];
const SS_COMMERCIAL = ['Financial Accounting', 'Commerce'];

/** A class's default names, judged by its name alone. */
function defaultNamesFor(string $className): array
{
    return array_keys(DefaultSubjects::forClassName(0, $className));
}

test('each class gets its own list from config/subjects.php, in result-sheet order', function () {
    expect(defaultNamesFor('KG 1'))->toBe(KG_SUBJECTS)
        ->and(defaultNamesFor('Reception'))->toBe(KG_SUBJECTS)
        ->and(defaultNamesFor('Nursery 1'))->toBe(NURSERY1_SUBJECTS)
        ->and(defaultNamesFor('Nursery 2'))->toBe(NURSERY2_SUBJECTS)
        ->and(defaultNamesFor('Primary 1'))->toBe(PRIMARY_SUBJECTS)
        ->and(defaultNamesFor('Primary 6'))->toBe(PRIMARY_SUBJECTS)
        ->and(defaultNamesFor('JSS 1'))->toBe(JSS1_SUBJECTS)
        ->and(defaultNamesFor('JSS 2'))->toBe(JSS2_3_SUBJECTS)
        ->and(defaultNamesFor('JSS 3'))->toBe(JSS2_3_SUBJECTS)
        ->and(defaultNamesFor('SS 1 Science'))->toBe([...SS_SUBJECTS, ...SS_SCIENCE])
        ->and(defaultNamesFor('SS 2 Art'))->toBe([...SS_SUBJECTS, ...SS_ART])
        ->and(defaultNamesFor('SS 3 Commercial'))->toBe([...SS_SUBJECTS, ...SS_COMMERCIAL])
        ->and(defaultNamesFor('SS 1'))->toBe(SS_SUBJECTS)
        ->and(defaultNamesFor('Nursery 3'))->toBe([])
        ->and(defaultNamesFor('Creche'))->toBe([]);
});

test('class names are recognised however they are written', function (string $name, ?string $expected) {
    expect(DefaultSubjects::groupFor($name, null, fn () => null))->toBe($expected);
})->with([
    ['JSS 1', 'jss1'], ['jss1', 'jss1'], ['JS 1', 'jss1'], ['JSS 1 Gold', 'jss1'], ['Junior Secondary 1', 'jss1'],
    ['JSS 2', 'jss2_3'], ['J.S.S. 3', 'jss2_3'], ['Junior Secondary 2', 'jss2_3'],
    ['SS 1 Science', 'ss'], ['SSS 2', 'ss'], ['S.S.S 3 Art', 'ss'], ['Senior Secondary 1', 'ss'],
    ['KG 1', 'kg'], ['Reception', 'kg'], ['Kindergarten 2', 'kg'],
    ['Nursery 1', 'nursery1'], ['Nursery 2', 'nursery2'], ['Primary 4', 'primary'], ['Basic 6', 'primary'],
    ['Nursery 3', null], ['Creche', null], ['JSS 10', null],
]);

test('a stream comes from the class, or from the word in its name', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'SSS']);
    $gold = SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'SS 1 Gold', 'stream' => ClassStream::Commercial]);

    expect(array_keys(DefaultSubjects::forClass($gold)))->toBe([...SS_SUBJECTS, ...SS_COMMERCIAL])
        ->and(defaultNamesFor('SSS 2 Arts'))->toBe([...SS_SUBJECTS, ...SS_ART]);
});

test('a newly set up school has the defaults for every class with a list', function () {
    DefaultAcademicStructure::seedFor($this->school);

    expect($this->school->offeredSubjectsFor('Nursery 1')->all())->toBe(NURSERY1_SUBJECTS)
        ->and($this->school->offeredSubjectsFor('Nursery 2')->all())->toBe(NURSERY2_SUBJECTS);
    foreach (['Primary 1', 'Primary 2', 'Primary 3', 'Primary 4', 'Primary 5', 'Primary 6'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(PRIMARY_SUBJECTS);
    }

    foreach (['KG 1', 'KG 2'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(KG_SUBJECTS);
    }
    expect($this->school->offeredSubjectsFor('JSS 1')->all())->toBe(JSS1_SUBJECTS);
    foreach (['JSS 2', 'JSS 3'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(JSS2_3_SUBJECTS);
    }
    foreach (['1', '2', '3'] as $year) {
        expect($this->school->offeredSubjectsFor("SS {$year} Science")->all())->toBe([...SS_SUBJECTS, ...SS_SCIENCE])
            ->and($this->school->offeredSubjectsFor("SS {$year} Art")->all())->toBe([...SS_SUBJECTS, ...SS_ART])
            ->and($this->school->offeredSubjectsFor("SS {$year} Commercial")->all())->toBe([...SS_SUBJECTS, ...SS_COMMERCIAL]);
    }

    // Other classes are left for the school to set up.
    foreach (['Creche', 'Nursery 3'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->isEmpty())->toBeTrue();
    }
});

test('a school that registers gets the default subjects', function () {
    $this->post(route('register'), [
        'school_name' => 'Brightfield College',
        'email' => 'admin@brightfield.example',
        'phone' => '+234 801 234 5678',
        'address' => '12 Allen Avenue, Ikeja, Lagos',
        'password' => 'Password123!',
        'password_confirmation' => 'Password123!',
        'terms' => '1',
    ])->assertRedirect();

    $school = School::where('name', 'Brightfield College')->firstOrFail();

    expect($school->offeredSubjectsFor('KG 1')->all())->toBe(KG_SUBJECTS)
        ->and($school->offeredSubjectsFor('JSS 1')->all())->toBe(JSS1_SUBJECTS)
        ->and($school->offeredSubjectsFor('JSS 3')->all())->toBe(JSS2_3_SUBJECTS)
        ->and($school->offeredSubjectsFor('SS 1 Science')->all())->toBe([...SS_SUBJECTS, ...SS_SCIENCE]);
});

test('a class added later starts with its default subjects', function () {
    $jss = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);
    $sss = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'SSS']);

    $this->actingAs($this->admin)->post(route('academics.classes.store', $jss), ['name' => 'JSS 1 Gold'])->assertRedirect();
    $this->actingAs($this->admin)->post(route('academics.classes.store', $sss), ['name' => 'SS 2 Gold', 'stream' => 'commercial'])->assertRedirect();

    expect($this->school->offeredSubjectsFor('JSS 1 Gold')->all())->toBe(JSS1_SUBJECTS)
        ->and($this->school->offeredSubjectsFor('SS 2 Gold')->all())->toBe([...SS_SUBJECTS, ...SS_COMMERCIAL]);
});

test('a class filed under the JSS level counts by its place in the level, whatever its name', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Junior Secondary']);

    $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => 'Year 7']);
    $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => 'Year 8']);

    expect($this->school->offeredSubjectsFor('Year 7')->all())->toBe(JSS1_SUBJECTS)
        ->and($this->school->offeredSubjectsFor('Year 8')->all())->toBe(JSS2_3_SUBJECTS);
});

test('a class with no default list gets no default subjects', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Nursery']);

    foreach (['Nursery 1', 'Nursery 2', 'Nursery 3'] as $name) {
        $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => $name]);
    }

    // Nursery 3 is third under the level, but its own name says which
    // class it is, and no list is for it.
    expect($this->school->offeredSubjectsFor('Nursery 3')->isEmpty())->toBeTrue()
        ->and($this->school->offeredSubjectsFor('Nursery 2')->all())->toBe(NURSERY2_SUBJECTS);
});

test('an existing school keeps its own subjects and gets whichever defaults a class is missing', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'JSS 2']);
    $robotics = Subject::factory()->create(['name' => 'Robotics']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $robotics->id]);

    expect(DefaultSubjects::ensureFor($this->school))->toBe(count(JSS2_3_SUBJECTS));

    // The defaults in result-sheet order, then the school's own extra subject.
    expect($this->school->offeredSubjectsFor('JSS 2')->all())->toBe([...JSS2_3_SUBJECTS, 'Robotics']);

    // Running it again adds nothing twice.
    expect(DefaultSubjects::ensureFor($this->school))->toBe(0);
});

test('a subject taken off a list leaves a class only where no score has been recorded for it', function () {
    DefaultAcademicStructure::seedFor($this->school);

    // A Music score is on record for JSS 1.
    $student = Student::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $examination = Examination::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 1']);
    $music = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'name' => 'Music', 'max_score' => 100]);
    ExaminationScore::factory()->create(['examination_subject_id' => $music->id, 'student_id' => $student->id, 'test_score' => 20, 'exam_score' => 40, 'score' => 60]);

    // Music and Diction come off the JSS 1 list.
    $jss1 = config('subjects.class_defaults.jss1.subjects');
    unset($jss1['Music'], $jss1['Diction']);
    config(['subjects.class_defaults.jss1.subjects' => $jss1]);

    expect(DefaultSubjects::ensureFor($this->school))->toBe(0);

    $offered = $this->school->offeredSubjectsFor('JSS 1');
    expect($offered->all())->not->toContain('Diction')
        // Kept, because a result depends on it, but now the school's own.
        ->toContain('Music')
        ->and(SubjectOffering::where('school_id', $this->school->id)->where('class_name', 'JSS 1')
            ->whereHas('subject', fn ($q) => $q->where('name', 'Music'))->value('is_default'))->toBeFalse();

    // Diction stays on the other lists it is still on.
    expect($this->school->offeredSubjectsFor('SS 1 Science')->all())->toContain('Diction');
});

test('the class subjects page lists a class\'s defaults, numbered and locked', function () {
    DefaultAcademicStructure::seedFor($this->school);

    foreach (['JSS 1' => JSS1_SUBJECTS, 'SS 1 Science' => [...SS_SUBJECTS, ...SS_SCIENCE], 'KG 1' => KG_SUBJECTS, 'Primary 4' => PRIMARY_SUBJECTS] as $class => $subjects) {
        $response = $this->actingAs($this->admin)
            ->get(route('class-subjects.index', ['class' => $class]))
            ->assertOk()
            ->assertSee("Default subjects for {$class}")
            ->assertSee("can't be removed", false)
            ->assertSeeInOrder($subjects);

        // A locked default is shown once and is not a submittable checkbox,
        // so it cannot be unticked from the form.
        $html = $response->getContent();
        $defaultIds = SubjectOffering::where('school_id', $this->school->id)->where('class_name', $class)->where('is_default', true)->pluck('subject_id');
        expect($defaultIds)->toHaveCount(count($subjects));
        foreach ($defaultIds as $id) {
            expect(substr_count($html, 'name="subject_ids[]" value="'.$id.'"'))->toBe(0);
        }
    }
});

test('the backfill command answers to its new name and its old one', function () {
    $this->artisan('subjects:backfill')->assertSuccessful();
    $this->artisan('subjects:backfill-jss')->assertSuccessful();
});

test('the students list numbers each student down the page, continuing across pages', function () {
    foreach ([['Zainab', 'Bello'], ['Tunde', 'Adebayo'], ['Ada', 'Bello']] as [$first, $last]) {
        Student::factory()->create(['school_id' => $this->school->id, 'first_name' => $first, 'last_name' => $last]);
    }

    $this->actingAs($this->admin)
        ->get(route('students.index'))
        ->assertOk()
        ->assertSee('S/N')
        ->assertSeeInOrder(['>1<', 'Ada Bello', '>2<', 'Tunde Adebayo', '>3<', 'Zainab Bello'], false);
});
