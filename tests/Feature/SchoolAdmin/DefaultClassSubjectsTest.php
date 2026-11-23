<?php

/*
| Default subjects for every class, Reception to SS 3 (config/subjects.php):
| set up without the School Admin doing anything, locked, and in
| result-sheet order.
*/

use App\Enums\ClassStream;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\DefaultClassSubjects;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

const RECEPTION_SUBJECTS = [
    'Numeracy', 'Literacy', 'Pre-Science', 'Protective Measures', 'Safety Measures',
    'Health Habits', 'Social Habits', 'Bible Story / CRS', 'Moral Instruction', 'Handwriting',
];

const NURSERY_1_SUBJECTS = [
    'Number Work', 'Letter Work', 'Moral Instruction', 'Science', 'Food and Nutrition',
    'Social Habits', 'Health Habits', 'CRS', 'Writing', 'Creative Arts',
];

const NURSERY_2_SUBJECTS = [
    'Number Work', 'Letter Work', 'Basic Science', 'Basic Technology', 'Social Habits', 'Health Habits', 'CRS',
    'Food and Nutrition', 'Quantitative Reasoning', 'Verbal Reasoning', 'Civic Education',
    'Agricultural Science', 'Writing',
];

const PRIMARY_SUBJECTS = [
    'Mathematics', 'English', 'History', 'CCA', 'French', 'Quantitative Reasoning',
    'Verbal Reasoning', 'Social Studies', 'CRS', 'Civic Education', 'Basic Science', 'Basic Technology',
    'Phonics', 'Yoruba / Igbo', 'Handwriting', 'Computer', 'Music',
];

const JSS_1_SUBJECTS = [
    'English Language', 'Mathematics', 'Intermediate Science', 'Social and Citizenship Studies',
    'Nigerian History', 'Business Studies', 'CRS', 'PHE', 'Diction', 'French', 'Music',
    'Fashion Design', 'Digital Technology', 'Yoruba',
];

const JSS_2_3_SUBJECTS = [
    'English Language', 'Mathematics', 'PHE', 'ICT', 'Basic Science', 'Basic Technology',
    'Business Studies', 'Social Studies', 'CRS', 'Civic Education', 'Music', 'French', 'Yoruba',
    'Agricultural Science', 'Home Economics', 'Phonics',
];

const SSS_GENERAL_SUBJECTS = [
    'English Language', 'Mathematics', 'Biology', 'Civic Education', 'Economics',
    'Digital Technology', 'Diction', 'Agricultural Science', 'Geography', 'Food and Nutrition',
];

test('every list comes from config/subjects.php, in result-sheet order', function () {
    expect(DefaultClassSubjects::namesForGroup('reception'))->toBe(RECEPTION_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('nursery_1'))->toBe(NURSERY_1_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('nursery_2'))->toBe(NURSERY_2_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('primary'))->toBe(PRIMARY_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('jss_1'))->toBe(JSS_1_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('jss_2_3'))->toBe(JSS_2_3_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('sss'))->toBe(SSS_GENERAL_SUBJECTS)
        ->and(DefaultClassSubjects::namesForGroup('sss_science'))->toBe([...SSS_GENERAL_SUBJECTS, 'Physics', 'Chemistry', 'Further Mathematics'])
        ->and(DefaultClassSubjects::namesForGroup('sss_art'))->toBe([...SSS_GENERAL_SUBJECTS, 'CRS', 'Government', 'Literature in English'])
        ->and(DefaultClassSubjects::namesForGroup('sss_commercial'))->toBe([...SSS_GENERAL_SUBJECTS, 'Financial Accounting', 'Commerce']);
});

test('a newly set up school has the default subjects for every class, with nothing for the admin to do', function () {
    DefaultAcademicStructure::seedFor($this->school);

    $expected = [
        'Creche' => RECEPTION_SUBJECTS,
        'KG 1' => RECEPTION_SUBJECTS,
        'KG 2' => RECEPTION_SUBJECTS,
        'Nursery 1' => NURSERY_1_SUBJECTS,
        'Nursery 2' => NURSERY_2_SUBJECTS,
        'Nursery 3' => NURSERY_2_SUBJECTS,
        'Primary 1' => PRIMARY_SUBJECTS,
        'Primary 6' => PRIMARY_SUBJECTS,
        'JSS 1' => JSS_1_SUBJECTS,
        'JSS 2' => JSS_2_3_SUBJECTS,
        'JSS 3' => JSS_2_3_SUBJECTS,
        'SS 1 Science' => [...SSS_GENERAL_SUBJECTS, 'Physics', 'Chemistry', 'Further Mathematics'],
        'SS 2 Art' => [...SSS_GENERAL_SUBJECTS, 'CRS', 'Government', 'Literature in English'],
        'SS 3 Commercial' => [...SSS_GENERAL_SUBJECTS, 'Financial Accounting', 'Commerce'],
    ];

    foreach ($expected as $class => $subjects) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe($subjects, $class);
    }

    // Every one of them is a locked default.
    expect(SubjectOffering::where('school_id', $this->school->id)->where('is_default', false)->exists())->toBeFalse();
});

test('a school that registers gets the default subjects for every class', function () {
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

    expect($school->offeredSubjectsFor('Nursery 1')->all())->toBe(NURSERY_1_SUBJECTS)
        ->and($school->offeredSubjectsFor('Primary 3')->all())->toBe(PRIMARY_SUBJECTS)
        ->and($school->offeredSubjectsFor('JSS 1')->all())->toBe(JSS_1_SUBJECTS);
});

test('a class added later starts with its default subjects', function (string $level, string $class, array $subjects) {
    $academicLevel = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => $level]);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $academicLevel), ['name' => $class])
        ->assertRedirect();

    expect($this->school->offeredSubjectsFor($class)->all())->toBe($subjects);
})->with([
    ['Nursery', 'Nursery 2 Gold', NURSERY_2_SUBJECTS],
    ['Primary', 'Primary 3B', PRIMARY_SUBJECTS],
    ['Primary', 'Primary 4B', ['Mathematics', 'English', 'BST', 'RNV', 'CCA', 'History', 'Phonics', 'Music', 'French', 'Computer', 'Yoruba', 'Igbo', 'Quantitative Reasoning', 'Verbal Reasoning', 'Handwriting']],
    ['JSS', 'JSS 1 Gold', JSS_1_SUBJECTS],
    ['Junior Secondary', 'Year 8', JSS_2_3_SUBJECTS],
    ['SSS', 'SS 2 Science', [...SSS_GENERAL_SUBJECTS, 'Physics', 'Chemistry', 'Further Mathematics']],
]);

test('an SS class takes its department from its stream when its name does not say', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'SSS']);
    $class = SchoolClass::factory()->create([
        'school_id' => $this->school->id,
        'academic_level_id' => $level->id,
        'name' => 'SS 1 Gold',
        'stream' => ClassStream::Commercial,
    ]);

    DefaultClassSubjects::ensureForClass($class);

    expect($this->school->offeredSubjectsFor('SS 1 Gold')->all())->toBe([...SSS_GENERAL_SUBJECTS, 'Financial Accounting', 'Commerce']);
});

test('a class the platform does not recognise gets no default subjects', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Clubs']);

    $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => 'Chess Club']);

    expect($this->school->offeredSubjectsFor('Chess Club')->isEmpty())->toBeTrue();
});

test('class names are recognised however they are written', function (string $name, ?string $level, ?string $expected) {
    expect(DefaultClassSubjects::groupFor($name, $level))->toBe($expected);
})->with([
    ['Reception', null, 'reception'], ['KG 2', null, 'reception'], ['Kindergarten', null, 'reception'],
    ['Creche', null, 'reception'], ['Pre-Nursery', null, 'reception'],
    ['Nursery 1', null, 'nursery_1'], ['Nursery 2', null, 'nursery_2'], ['Nursery 3', null, 'nursery_2'],
    ['Primary 1', null, 'primary'], ['Pry 5', null, 'primary_4_5'], ['Basic 4', null, 'primary_4_5'], ['Primary 6', null, 'primary'], ['Basic 3', null, 'primary'],
    ['JSS 1', null, 'jss_1'], ['jss2', null, 'jss_2_3'], ['J.S.S. 3', null, 'jss_2_3'], ['JS 1', null, 'jss_1'],
    ['Basic 7', null, 'jss_1'], ['Basic 9', null, 'jss_2_3'], ['Year 7', 'JSS', 'jss_1'], ['Year 9', 'Junior Secondary', 'jss_2_3'],
    ['SS 1 Science', null, 'sss_science'], ['SSS 2 Art', null, 'sss_art'], ['S.S. 3 Commercial', null, 'sss_commercial'],
    ['SS 1', null, 'sss'], ['Year 10', 'Senior Secondary', 'sss'],
    ['Chess Club', null, null],
]);

test('an existing school keeps its own subjects, gets whichever defaults a class is missing, and loses old defaults no longer listed', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'JSS 2']);

    // The school's own subject, and an old JSS default the platform had put on the class.
    $robotics = Subject::factory()->create(['name' => 'Robotics']);
    $security = Subject::factory()->create(['name' => 'Security Education']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $robotics->id]);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $security->id, 'is_default' => true]);

    expect(DefaultClassSubjects::ensureFor($this->school))->toBe(count(JSS_2_3_SUBJECTS));

    // The defaults in result-sheet order, then the school's own extra subject.
    expect($this->school->offeredSubjectsFor('JSS 2')->all())->toBe([...JSS_2_3_SUBJECTS, 'Robotics']);

    // Running it again adds nothing twice.
    expect(DefaultClassSubjects::ensureFor($this->school))->toBe(0);
});

test('the class subjects page lists the class\'s default subjects, numbered and locked', function (string $class, array $subjects, string $label) {
    DefaultAcademicStructure::seedFor($this->school);

    $response = $this->actingAs($this->admin)
        ->get(route('class-subjects.index', ['class' => $class]))
        ->assertOk()
        ->assertSee('Default subjects for '.$label, false)
        ->assertSee("can't be removed", false)
        ->assertSeeInOrder($subjects, false);

    // A locked default is shown once and is not a submittable checkbox, so
    // it cannot be unticked from the form.
    $html = $response->getContent();
    $ids = SubjectOffering::where('school_id', $this->school->id)->where('class_name', $class)->pluck('subject_id');
    foreach ($ids as $id) {
        expect(substr_count($html, 'name="subject_ids[]" value="'.$id.'"'))->toBe(0);
    }
})->with([
    ['Nursery 1', NURSERY_1_SUBJECTS, 'Nursery 1'],
    ['Primary 2', PRIMARY_SUBJECTS, 'Primary'],
    ['JSS 1', JSS_1_SUBJECTS, 'JSS 1'],
]);

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
