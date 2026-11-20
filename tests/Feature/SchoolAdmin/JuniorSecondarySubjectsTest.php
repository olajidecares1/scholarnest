<?php

use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\Subject;
use App\Models\SubjectOffering;
use App\Models\User;
use App\Services\DefaultAcademicStructure;
use App\Services\JuniorSecondarySubjects;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

const JSS_SUBJECTS = [
    'English Language', 'Mathematics', 'Basic Science', 'Basic Technology',
    'Agricultural Science', 'Home Economics', 'Social Studies', 'PHE',
    'Computer Science', 'Yoruba', 'French', 'Civic Education', 'Security Education',
    'Business Studies', 'Christian Religious Studies', 'Islamic Religious Studies',
    'History', 'Music', 'Cultural & Creative Art',
];

test('the default JSS subjects come from config/subjects.php, in result-sheet order', function () {
    expect(JuniorSecondarySubjects::names())->toBe(JSS_SUBJECTS);
});

test('a newly set up school has all the default subjects for every JSS class', function () {
    DefaultAcademicStructure::seedFor($this->school);

    foreach (['JSS 1', 'JSS 2', 'JSS 3'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(JSS_SUBJECTS);
    }

    // Other classes are left for the school to set up.
    expect($this->school->offeredSubjectsFor('Primary 1')->isEmpty())->toBeTrue();
    expect($this->school->offeredSubjectsFor('SS 1 Science')->isEmpty())->toBeTrue();
});

test('a school that registers gets the default JSS subjects', function () {
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

    expect($school->offeredSubjectsFor('JSS 1')->all())->toBe(JSS_SUBJECTS);
});

test('a JSS class added later starts with the default subjects', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'JSS 1 Gold'])
        ->assertRedirect();

    expect($this->school->offeredSubjectsFor('JSS 1 Gold')->all())->toBe(JSS_SUBJECTS);
});

test('a class filed under the JSS level counts as JSS even with another name', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Junior Secondary']);

    $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => 'Year 7']);

    expect($this->school->offeredSubjectsFor('Year 7')->count())->toBe(count(JSS_SUBJECTS));
});

test('a class that is not JSS gets no default subjects', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'SSS']);

    $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => 'SS 1 Science']);

    expect($this->school->offeredSubjectsFor('SS 1 Science')->isEmpty())->toBeTrue();
});

test('JSS class names are recognised however they are written', function (string $name, bool $expected) {
    expect(JuniorSecondarySubjects::isJuniorClass($name))->toBe($expected);
})->with([
    ['JSS 1', true], ['jss2', true], ['J.S.S. 3', true], ['JS 1', true], ['Junior Secondary 2', true],
    ['SS 1 Science', false], ['SSS 2', false], ['Primary 4', false], ['Nursery 1', false],
]);

test('an existing school keeps its own subjects and gets whichever defaults a JSS class is missing', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'JSS 2']);
    $robotics = Subject::factory()->create(['name' => 'Robotics']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $robotics->id]);

    expect(JuniorSecondarySubjects::ensureFor($this->school))->toBe(count(JSS_SUBJECTS));

    // The defaults in result-sheet order, then the school's own extra subject.
    expect($this->school->offeredSubjectsFor('JSS 2')->all())->toBe([...JSS_SUBJECTS, 'Robotics']);

    // Running it again adds nothing twice.
    expect(JuniorSecondarySubjects::ensureFor($this->school))->toBe(0);
});

test('the class subjects page lists the default JSS subjects, numbered and locked', function () {
    DefaultAcademicStructure::seedFor($this->school);

    $response = $this->actingAs($this->admin)
        ->get(route('class-subjects.index', ['class' => 'JSS 1']))
        ->assertOk()
        ->assertSee('Default Junior Secondary (JSS) subjects')
        ->assertSee("can't be removed", false)
        ->assertSeeInOrder(JSS_SUBJECTS);

    // A locked default is shown once and is not a submittable checkbox, so
    // it cannot be unticked from the form.
    $html = $response->getContent();
    foreach (JuniorSecondarySubjects::catalogueSubjects() as $subject) {
        expect(substr_count($html, 'name="subject_ids[]" value="'.$subject->id.'"'))->toBe(0);
    }
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
