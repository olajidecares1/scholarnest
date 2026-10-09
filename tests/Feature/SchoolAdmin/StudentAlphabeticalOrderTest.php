<?php

use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\AttendanceRecord;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;
use Illuminate\Http\UploadedFile;

beforeEach(function () {
    $this->school = School::factory()->create(['auto_generate_admission_numbers' => false]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);

    $level = AcademicLevel::factory()->create(['school_id' => $this->school->id]);
    SchoolClass::factory()->create(['school_id' => $this->school->id, 'academic_level_id' => $level->id, 'name' => 'Primary 3']);
});

test('the alphabetical scope orders by first name, then surname, as names are shown', function () {
    foreach ([['Zainab', 'Bello'], ['Tunde', 'Adebayo'], ['Ada', 'Bello'], ['Chidi', 'Okafor']] as [$first, $last]) {
        Student::factory()->create(['school_id' => $this->school->id, 'first_name' => $first, 'last_name' => $last]);
    }

    $names = $this->school->students()->alphabetical()->get()->map->fullName()->all();

    expect($names)->toBe(['Ada Bello', 'Chidi Okafor', 'Tunde Adebayo', 'Zainab Bello']);
});

test('a loaded list sorts the same way, ignoring case and stray spaces', function () {
    $students = collect([[' zainab', 'bello'], ['Tunde', 'Adebayo'], ['Ada', 'Bello']])
        ->map(fn ($n) => new Student(['first_name' => $n[0], 'last_name' => $n[1]]))
        ->all();

    $sorted = Student::sortAlphabetically($students)->map(fn ($s) => $s->first_name)->all();

    expect($sorted)->toBe(['Ada', 'Tunde', ' zainab']);
});

test('the students list shows every school its own students alphabetically', function () {
    $other = School::factory()->create();
    Student::factory()->create(['school_id' => $other->id, 'first_name' => 'Aaron', 'last_name' => 'Aaronson']);

    foreach ([['Zainab', 'Bello'], ['Tunde', 'Adebayo'], ['Chidi', 'Okafor'], ['Ada', 'Bello']] as [$first, $last]) {
        Student::factory()->create(['school_id' => $this->school->id, 'first_name' => $first, 'last_name' => $last]);
    }

    $this->actingAs($this->admin)
        ->get(route('students.index'))
        ->assertOk()
        ->assertDontSee('Aaronson')
        ->assertSeeInOrder(['Ada Bello', 'Chidi Okafor', 'Tunde Adebayo', 'Zainab Bello']);
});

test('a bulk upload is reviewed and imported alphabetically, whatever the file order', function () {
    $this->school->update(['school_code' => 'GHS', 'current_session' => '2026/2027', 'auto_generate_admission_numbers' => true]);

    $csv = "First Name,Last Name,Gender\nZainab,Bello,F\nChidi,Okafor,M\nTunde,Adebayo,M\nAda,Bello,F\n";

    $response = $this->actingAs($this->admin)->post(route('students.import.preview'), [
        'class_name' => 'Primary 3',
        'file' => UploadedFile::fake()->createWithContent('list.csv', $csv),
    ]);
    $response->assertSessionHasNoErrors();
    preg_match('#/([A-Za-z0-9]{40})$#', $response->headers->get('Location'), $m);

    $this->actingAs($this->admin)
        ->get(route('students.import.review', ['token' => $m[1]]))
        ->assertOk()
        ->assertSeeInOrder(['Ada', 'Chidi', 'Tunde', 'Zainab']);

    $this->actingAs($this->admin)->post(route('students.import.store', ['token' => $m[1]]))->assertSessionHasNoErrors();

    // Created, and so numbered, in register order.
    $created = Student::where('school_id', $this->school->id)->orderBy('id')->get();
    expect($created->map->fullName()->all())->toBe(['Ada Bello', 'Chidi Okafor', 'Tunde Adebayo', 'Zainab Bello']);

    $numbers = $created->pluck('admission_number')->all();
    $sortedNumbers = $numbers;
    natsort($sortedNumbers);
    expect(array_values($sortedNumbers))->toBe($numbers);

    $this->actingAs($this->admin)
        ->get(route('students.index', ['class' => 'Primary 3']))
        ->assertSeeInOrder(['Ada Bello', 'Chidi Okafor', 'Tunde Adebayo', 'Zainab Bello']);
});

test('the attendance register and its history list the class alphabetically', function () {
    foreach ([['Zainab', 'Bello'], ['Tunde', 'Adebayo'], ['Chidi', 'Okafor'], ['Ada', 'Bello']] as [$first, $last]) {
        Student::factory()->create(['school_id' => $this->school->id, 'first_name' => $first, 'last_name' => $last, 'class_name' => 'Primary 3', 'is_active' => true]);
    }

    $this->actingAs($this->admin)
        ->get(route('attendance.index', ['class' => 'Primary 3']))
        ->assertOk()
        ->assertSeeInOrder(['Ada Bello', 'Chidi Okafor', 'Tunde Adebayo', 'Zainab Bello']);

    foreach (Student::where('school_id', $this->school->id)->orderByDesc('id')->get() as $student) {
        AttendanceRecord::factory()->create(['school_id' => $this->school->id, 'student_id' => $student->id, 'class_name' => 'Primary 3', 'date' => '2026-10-05']);
    }

    $this->actingAs($this->admin)
        ->get(route('attendance.history'))
        ->assertOk()
        ->assertSeeInOrder(['Ada Bello', 'Chidi Okafor', 'Tunde Adebayo', 'Zainab Bello']);
});
