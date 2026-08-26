<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\Plan;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\SchoolClass;
use App\Models\Student;
use App\Models\User;

/**
 * Tokens are generated for a CLASS, and tracked as a class.
 *
 * A School Admin at the end of term is not thinking about thirty individual
 * tokens; they are thinking about SSS 1 Science. They pick the class from
 * their own class list - never typing it - and the system works out how many
 * students are in it and issues that many. Then they need to see who has used
 * theirs and who has not.
 */
function tokenClassSchool(string $className = 'SSS 1 Science', int $students = 3): array
{
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);

    $level = AcademicLevel::factory()->create(['school_id' => $school->id]);
    SchoolClass::factory()->create([
        'school_id' => $school->id,
        'academic_level_id' => $level->id,
        'name' => $className,
    ]);

    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => $className,
        'term' => ExamTerm::Second->value,
        'session' => '2025/2026',
    ]);

    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);

    $roll = collect(range(1, $students))->map(function () use ($school, $className, $subject) {
        $student = Student::factory()->create([
            'school_id' => $school->id,
            'class_name' => $className,
            'is_active' => true,
        ]);

        ExaminationScore::factory()->create([
            'examination_subject_id' => $subject->id,
            'student_id' => $student->id,
            'score' => 70,
        ]);

        return $student;
    });

    return [$school->fresh(), $admin, $examination, $roll];
}

// -----------------------------------------------------------------------------
// Choosing the class
// -----------------------------------------------------------------------------

test('the class comes from the school\'s own list, and cannot be typed', function () {
    [$school, $admin, $examination] = tokenClassSchool();

    // A class the school does not have. Refused by the server, not merely
    // absent from the dropdown - otherwise a batch could be issued against
    // "SSS1 Science" while every student is filed under "SSS 1 Science",
    // producing nothing and no obvious reason why.
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'SSS1 Science',
            'examination_id' => $examination->id,
        ])
        ->assertSessionHasErrors('class_name');

    expect(ResultCheckingPin::count())->toBe(0);
});

test('the chosen class must be the examination\'s class', function () {
    [$school, $admin, $examination] = tokenClassSchool();

    $otherLevel = AcademicLevel::factory()->create(['school_id' => $school->id]);
    SchoolClass::factory()->create([
        'school_id' => $school->id,
        'academic_level_id' => $otherLevel->id,
        'name' => 'JSS 1',
    ]);

    // Both are real classes; they are not the same class. A dropdown that did
    // not have to agree with the examination would be decoration.
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'JSS 1',
            'examination_id' => $examination->id,
        ])
        ->assertSessionHasErrors('examination_id');

    expect(ResultCheckingPin::count())->toBe(0);
});

test('every class the school uses is offered, including one never entered into the academic structure', function () {
    [$school, $admin] = tokenClassSchool();

    // A class with students but no SchoolClass row - a school that imported
    // its students, or whose records predate the academic structure. A picker
    // that cannot offer the class you need is worse than the free-text field
    // it replaced.
    Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Nursery 2',
        'is_active' => true,
    ]);

    expect($school->fresh()->configuredClassNames())
        ->toContain('SSS 1 Science')
        ->toContain('Nursery 2');

    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertOk()
        ->assertSee('SSS 1 Science')
        ->assertSee('Nursery 2');
});

// -----------------------------------------------------------------------------
// Generating one per student
// -----------------------------------------------------------------------------

test('choosing a class generates one token per student in it', function () {
    [$school, $admin, $examination, $roll] = tokenClassSchool('SSS 1 Science', 30);

    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'SSS 1 Science',
            'examination_id' => $examination->id,
        ])
        ->assertRedirect();

    // Thirty students, thirty tokens - the School Admin created none of them
    // by hand.
    expect(ResultCheckingPin::count())->toBe(30)
        ->and(ResultCheckingPin::pluck('bound_student_id')->unique())->toHaveCount(30)
        ->and(ResultCheckingPin::pluck('token_hash')->unique())->toHaveCount(30);

    // And each is still 15 characters, still bound to one student.
    expect(ResultCheckingPin::pluck('bound_student_id')->sort()->values()->all())
        ->toBe($roll->pluck('id')->sort()->values()->all());
});

test('running it again tops the class up rather than duplicating it', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    $issue = fn () => $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    $issue();
    expect(ResultCheckingPin::count())->toBe(3);

    // A student admitted after the batch was generated.
    Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SSS 1 Science',
        'is_active' => true,
    ]);

    $issue();

    // Four tokens, not seven. Running it twice is safe.
    expect(ResultCheckingPin::count())->toBe(4)
        ->and(ResultCheckingPin::pluck('bound_student_id')->unique())->toHaveCount(4);
});

test('a class with no students generates nothing and says so', function () {
    [$school, $admin] = tokenClassSchool('SSS 1 Science', 0);

    $level = AcademicLevel::factory()->create(['school_id' => $school->id]);
    SchoolClass::factory()->create(['school_id' => $school->id, 'academic_level_id' => $level->id, 'name' => 'Creche']);

    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'Creche',
        'term' => ExamTerm::First->value,
        'session' => '2025/2026',
    ]);

    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), ['class_name' => 'Creche', 'examination_id' => $examination->id])
        ->assertRedirect()
        ->assertSessionHas('status');

    expect(ResultCheckingPin::count())->toBe(0);
});

// -----------------------------------------------------------------------------
// Tracking the batch
// -----------------------------------------------------------------------------

test('the school can see how many tokens are used and how many are not', function () {
    [$school, $admin, $examination, $roll] = tokenClassSchool('SSS 1 Science', 4);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    // Two of the four use theirs.
    foreach ($roll->take(2) as $student) {
        $token = ResultCheckingPin::where('bound_student_id', $student->id)->firstOrFail();
        $plain = $token->plainToken();

        identifyForResultCheck($school, $student);
        $this->post(route('check-result.verify', $school), ['code' => $plain]);
    }

    $response = $this->actingAs($admin)
        ->get(route('result-pins.index', ['track_examination_id' => $examination->id]))
        ->assertOk();

    expect($response->viewData('classTracking')['totals'])->toMatchArray([
        'students' => 4,
        'issued' => 4,
        'used' => 2,
        'unused' => 2,
        'missing' => 0,
    ]);

    // Named, not merely counted: the whole point is knowing WHO still needs
    // chasing.
    $response->assertSee($roll->first()->fullName())
        ->assertSee($roll->last()->fullName())
        ->assertSee('Used')
        ->assertSee('Not used');
});

test('a student with no token yet shows as a gap, not as an absence', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 2);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    // Admitted after the batch. Built from the students rather than from the
    // tokens, so "2 issued of 3 students" is visible - which it would not be
    // in a list that only contained tokens.
    $latecomer = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SSS 1 Science',
        'is_active' => true,
    ]);

    $response = $this->actingAs($admin)
        ->get(route('result-pins.index', ['track_examination_id' => $examination->id]))
        ->assertOk();

    expect($response->viewData('classTracking')['totals'])
        ->toMatchArray(['students' => 3, 'issued' => 2, 'missing' => 1]);

    $response->assertSee($latecomer->fullName())->assertSee('No token');
});

test('the school sees which guardian used a token, and when', function () {
    [$school, $admin, $examination, $roll] = tokenClassSchool('SSS 1 Science', 1);
    activateSchool($school, PlanKey::Standard);

    $student = $roll->first();

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    $guardian = Guardian::factory()->create(['school_id' => $school->id, 'is_active' => true, 'name' => 'Ngozi Adeyemi']);
    $guardian->students()->attach($student->id);

    $plain = ResultCheckingPin::where('bound_student_id', $student->id)->firstOrFail()->plainToken();

    $this->actingAs($guardian, 'guardian')->post(
        route('guardian.children.results.unlock', [$school, $student, $examination]),
        ['token' => $plain],
    )->assertSessionHasNoErrors();

    // "Who used it" has an answer in a portal, where somebody is signed in.
    $this->actingAs($admin)
        ->get(route('result-pins.index', ['track_examination_id' => $examination->id]))
        ->assertOk()
        ->assertSee('Ngozi Adeyemi')
        ->assertSee('parent/guardian');
});

test('a token redeemed from the public result link records no signed-in user', function () {
    [$school, $admin, $examination, $roll] = tokenClassSchool('SSS 1 Science', 1);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    $plain = ResultCheckingPin::where('bound_student_id', $roll->first()->id)->firstOrFail()->plainToken();
    identifyForResultCheck($school, $roll->first());
    $this->post(route('check-result.verify', $school), ['code' => $plain]);

    // Nobody is signed in on that page by design - the token IS the
    // credential - so the honest record is that there was no sign-in, not an
    // invented identity.
    $this->actingAs($admin)
        ->get(route('result-pins.index', ['track_examination_id' => $examination->id]))
        ->assertOk()
        ->assertSee('Result link (no sign-in)');
});

test('one school cannot track another school\'s class', function () {
    [, $admin] = tokenClassSchool();
    [, , $otherExamination] = tokenClassSchool('JSS 1');

    $response = $this->actingAs($admin)
        ->get(route('result-pins.index', ['track_examination_id' => $otherExamination->id]))
        ->assertOk();

    expect($response->viewData('classTracking'))->toBeNull();
});

// -----------------------------------------------------------------------------
// The batch stays inside its class, and stays current
// -----------------------------------------------------------------------------

test('a batch covers the chosen class and nobody else', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    // Another class at the same school, whose students must not be swept in.
    $elsewhere = Student::factory()->count(4)->create([
        'school_id' => $school->id,
        'class_name' => 'SSS 1 Commercial',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ])->assertSessionHasNoErrors();

    $holders = $school->resultCheckingPins()->pluck('bound_student_id');

    expect($holders)->toHaveCount(3)
        ->and($holders->intersect($elsewhere->pluck('id'))->all())->toBe([]);
});

test('a student who joins after the batch is picked up by the next run', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    $payload = ['class_name' => 'SSS 1 Science', 'examination_id' => $examination->id];
    $this->actingAs($admin)->post(route('result-pins.store-bulk'), $payload);

    Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SSS 1 Science',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), $payload);

    // Four students, four tokens - the three who already held one were
    // skipped rather than reissued.
    expect($school->resultCheckingPins()->count())->toBe(4);
});

test('every token in a class batch is fifteen characters and unique', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 6);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    $tokens = collect(session('issued_tokens'))->pluck('token');

    expect($tokens)->toHaveCount(6)
        ->and($tokens->unique())->toHaveCount(6);

    foreach ($tokens as $token) {
        // The same format as a single issue: session, term, then randomness.
        // A class batch is not a different kind of token.
        expect($token)->toHaveLength(ResultCheckingPin::TOKEN_LENGTH)
            ->and($token)->toStartWith('25262');
    }
});

test('the tracking panel never reprints the tokens themselves', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'examination_id' => $examination->id,
    ]);

    $issued = collect(session('issued_tokens'))->pluck('token');

    // Spend the one-time flash first: the tokens legitimately appear on the
    // response to generating them, and nowhere after it.
    $this->actingAs($admin)->get(route('result-pins.index'));

    $response = $this->actingAs($admin)
        ->get(route('result-pins.index', ['track_examination_id' => $examination->id]))
        ->assertOk();

    // Tokens are stored hashed, and a screen reprinting a whole class's worth
    // would be the one place they could all be taken at once.
    foreach ($issued as $token) {
        $response->assertDontSee($token);
    }
});

test('class token generation works the same on every plan', function (PlanKey $planKey) {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 2);

    // The helper activates on Basic; move the school onto the plan under test.
    $school->activeSubscription->update([
        'plan_id' => Plan::firstOrCreate(
            ['key' => $planKey],
            Plan::factory()->make(['key' => $planKey])->toArray(),
        )->id,
    ]);

    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'SSS 1 Science',
            'examination_id' => $examination->id,
        ])
        ->assertSessionHasNoErrors();

    expect($school->resultCheckingPins()->count())->toBe(2);
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);
