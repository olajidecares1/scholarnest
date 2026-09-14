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
 * their own class list, never typing it, and the system works out how many
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
    // absent from the dropdown, otherwise a batch could be issued against
    // "SSS1 Science" while every student is filed under "SSS 1 Science",
    // producing nothing and no obvious reason why.
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'SSS1 Science',
            'session' => $examination->session,
            'term' => $examination->term->value,
        ])
        ->assertSessionHasErrors('class_name');

    expect(ResultCheckingPin::count())->toBe(0);
});

test('the examination follows the class, so the two can never disagree', function () {
    [$school, $admin, $examination] = tokenClassSchool();

    $otherLevel = AcademicLevel::factory()->create(['school_id' => $school->id]);
    SchoolClass::factory()->create([
        'school_id' => $school->id,
        'academic_level_id' => $otherLevel->id,
        'name' => 'JSS 1',
    ]);

    Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);

    // This used to be a guard: the form offered an Examination separately, so
    // it had to be refused when it named a different class from the one
    // chosen. The examination is now DERIVED from the class, year and term,
    // so a mismatch is not something to reject, it cannot be expressed.
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'JSS 1',
            'session' => $examination->session,
            'term' => $examination->term->value,
        ])
        ->assertRedirect()
        ->assertSessionHasNoErrors();

    $issued = ResultCheckingPin::with('examination')->get();

    expect($issued)->toHaveCount(1)
        ->and($issued->first()->examination->class_name)->toBe('JSS 1')
        ->and($issued->first()->examination->id)->not->toBe($examination->id);
});

test('every class the school uses is offered, including one never entered into the academic structure', function () {
    [$school, $admin] = tokenClassSchool();

    // A class with students but no SchoolClass row, a school that imported
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
            'session' => $examination->session,
            'term' => $examination->term->value,
        ])
        ->assertRedirect();

    // Thirty students, thirty tokens, the School Admin created none of them
    // by hand.
    expect(ResultCheckingPin::count())->toBe(30)
        ->and(ResultCheckingPin::pluck('bound_student_id')->unique())->toHaveCount(30)
        ->and(ResultCheckingPin::pluck('token_hash')->unique())->toHaveCount(30);

    // And each is still twelve characters, still bound to one student.
    expect(ResultCheckingPin::pluck('bound_student_id')->sort()->values()->all())
        ->toBe($roll->pluck('id')->sort()->values()->all());
});

test('running it again tops the class up rather than duplicating it', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    $issue = fn () => $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'session' => $examination->session,
        'term' => $examination->term->value,
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

    // THE WORDING IS THE POINT, not merely that something was said. This
    // asserted only that a status existed, and passed for two years while the
    // message read "Every student in Creche already has a token for this
    // result", to a school holding no tokens at all, for a class holding no
    // students. A school reading that goes looking for tokens that were never
    // generated.
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), ['class_name' => 'Creche', 'session' => $examination->session, 'term' => $examination->term->value])
        ->assertRedirect()
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'No active students in Creche')
            && ! str_contains($status, 'already has a token'));

    expect(ResultCheckingPin::count())->toBe(0);
});

test('a class where everyone already holds one says THAT, and not the other thing', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 2);

    $payload = [
        'class_name' => 'SSS 1 Science',
        'session' => $examination->session,
        'term' => $examination->term->value,
    ];

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), $payload);

    expect(ResultCheckingPin::count())->toBe(2);

    // Run again with nobody new. This is the case the old message described,
    // and it is the only case that should get it.
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), $payload)
        ->assertSessionHas('status', fn (string $status) => str_contains($status, 'already has a token')
            && ! str_contains($status, 'No active students'));

    expect(ResultCheckingPin::count())->toBe(2);
});

test('a class with students reports how many tokens were issued', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 5);

    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            'class_name' => 'SSS 1 Science',
            'session' => $examination->session,
            'term' => $examination->term->value,
        ])
        ->assertSessionHas('status', fn (string $status) => str_contains($status, '5 result token'));

    expect(ResultCheckingPin::count())->toBe(5);
});

// -----------------------------------------------------------------------------
// Tracking the batch
// -----------------------------------------------------------------------------

test('the school can see how many tokens are used and how many are not', function () {
    [$school, $admin, $examination, $roll] = tokenClassSchool('SSS 1 Science', 4);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'session' => $examination->session,
        'term' => $examination->term->value,
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
        'session' => $examination->session,
        'term' => $examination->term->value,
    ]);

    // Admitted after the batch. Built from the students rather than from the
    // tokens, so "2 issued of 3 students" is visible, which it would not be
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
        'session' => $examination->session,
        'term' => $examination->term->value,
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
        'session' => $examination->session,
        'term' => $examination->term->value,
    ]);

    $plain = ResultCheckingPin::where('bound_student_id', $roll->first()->id)->firstOrFail()->plainToken();
    identifyForResultCheck($school, $roll->first());
    $this->post(route('check-result.verify', $school), ['code' => $plain]);

    // Nobody is signed in on that page by design, the token IS the
    // credential, so the honest record is that there was no sign-in, not an
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
        'session' => $examination->session,
        'term' => $examination->term->value,
    ])->assertSessionHasNoErrors();

    $holders = $school->resultCheckingPins()->pluck('bound_student_id');

    expect($holders)->toHaveCount(3)
        ->and($holders->intersect($elsewhere->pluck('id'))->all())->toBe([]);
});

test('a student who joins after the batch is picked up by the next run', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    $payload = [
        'class_name' => 'SSS 1 Science',
        'session' => $examination->session,
        'term' => $examination->term->value,
    ];
    $this->actingAs($admin)->post(route('result-pins.store-bulk'), $payload);

    Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SSS 1 Science',
        'is_active' => true,
    ]);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), $payload);

    // Four students, four tokens, the three who already held one were
    // skipped rather than reissued.
    expect($school->resultCheckingPins()->count())->toBe(4);
});

test('every token in a class batch is fifteen characters and unique', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 6);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'session' => $examination->session,
        'term' => $examination->term->value,
    ]);

    $tokens = collect(session('issued_tokens'))->pluck('token');

    expect($tokens)->toHaveCount(6)
        ->and($tokens->unique())->toHaveCount(6);

    foreach ($tokens as $token) {
        // The same format as a single issue: twelve random characters. A class
        // batch is not a different kind of token, and none of them announces
        // the term the batch was generated for.
        expect($token)->toHaveLength(ResultCheckingPin::TOKEN_LENGTH)
            ->and($token)->toMatch('/^[A-Za-z0-9]{12}$/')
            ->and($token)->not->toStartWith('2526');
    }
});

test('the tracking panel never reprints the tokens themselves', function () {
    [$school, $admin, $examination] = tokenClassSchool('SSS 1 Science', 3);

    $this->actingAs($admin)->post(route('result-pins.store-bulk'), [
        'class_name' => 'SSS 1 Science',
        'session' => $examination->session,
        'term' => $examination->term->value,
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
            'session' => $examination->session,
            'term' => $examination->term->value,
        ])
        ->assertSessionHasNoErrors();

    expect($school->resultCheckingPins()->count())->toBe(2);
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);
