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
    'Computer Science', 'Yoruba', 'French', 'Civic Education',
    'Business Studies', 'CRS/IRS', 'History', 'Music', 'Cultural & Creative Art',
];

test('there are exactly 17 default JSS subjects, in result-sheet order', function () {
    expect(JuniorSecondarySubjects::names())->toBe(JSS_SUBJECTS);
});

test('a newly set up school has all 17 subjects ticked for every JSS class', function () {
    DefaultAcademicStructure::seedFor($this->school);

    foreach (['JSS 1', 'JSS 2', 'JSS 3'] as $class) {
        expect($this->school->offeredSubjectsFor($class)->all())->toBe(JSS_SUBJECTS);
    }

    // Other classes are left for the school to set up.
    expect($this->school->offeredSubjectsFor('Primary 1')->isEmpty())->toBeTrue();
    expect($this->school->offeredSubjectsFor('SS 1 Science')->isEmpty())->toBeTrue();
});

test('a school that registers gets the 17 JSS subjects by default', function () {
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

test('a JSS class added later starts with the 17 subjects', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);

    $this->actingAs($this->admin)
        ->post(route('academics.classes.store', $level), ['name' => 'JSS 1 Gold'])
        ->assertRedirect();

    expect($this->school->offeredSubjectsFor('JSS 1 Gold')->all())->toBe(JSS_SUBJECTS);
});

test('a class filed under the JSS level counts as JSS even with another name', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Junior Secondary']);

    $this->actingAs($this->admin)->post(route('academics.classes.store', $level), ['name' => 'Year 7']);

    expect($this->school->offeredSubjectsFor('Year 7')->count())->toBe(17);
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

test('an existing school keeps its own choices but gets whichever of the 17 a JSS class is missing', function () {
    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'JSS']);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'JSS 2']);
    $robotics = Subject::factory()->create(['name' => 'Robotics']);
    SubjectOffering::factory()->create(['school_id' => $this->school->id, 'class_name' => 'JSS 2', 'subject_id' => $robotics->id]);

    // Without addMissing, a class the school already set up is left alone.
    expect(JuniorSecondarySubjects::ensureFor($this->school))->toBe(0);

    expect(JuniorSecondarySubjects::ensureFor($this->school, addMissing: true))->toBe(17);

    // The 17 in result-sheet order, then the school's own extra subject.
    expect($this->school->offeredSubjectsFor('JSS 2')->all())->toBe([...JSS_SUBJECTS, 'Robotics']);

    // Running it again adds nothing twice.
    expect(JuniorSecondarySubjects::ensureFor($this->school, addMissing: true))->toBe(0);
});

test('the class subjects page shows the 17 JSS subjects ticked and numbered for a JSS class', function () {
    DefaultAcademicStructure::seedFor($this->school);

    $response = $this->actingAs($this->admin)
        ->get(route('class-subjects.index', ['class' => 'JSS 1']))
        ->assertOk()
        ->assertSee('Junior Secondary (JSS) subjects')
        ->assertSee('Tick all 17')
        ->assertSeeInOrder(JSS_SUBJECTS);

    // Each of the 17 appears once, so unticking one is not undone by a
    // second checkbox for the same subject further down the page.
    $html = $response->getContent();
    foreach (JuniorSecondarySubjects::catalogueSubjects() as $subject) {
        expect(substr_count($html, 'name="subject_ids[]" value="'.$subject->id.'"'))->toBe(1);
    }
});

test('a JSS subject the school unticks stays unticked', function () {
    DefaultAcademicStructure::seedFor($this->school);
    $keep = Subject::whereIn('name', array_diff(JSS_SUBJECTS, ['French']))->pluck('id')->all();

    $this->actingAs($this->admin)->post(route('class-subjects.store'), [
        'class_name' => 'JSS 1',
        'subject_ids' => $keep,
    ])->assertRedirect();

    $this->actingAs($this->admin)->get(route('class-subjects.index', ['class' => 'JSS 1']))->assertOk();

    expect($this->school->offeredSubjectsFor('JSS 1')->all())->toBe(array_values(array_diff(JSS_SUBJECTS, ['French'])));
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
