<?php

use App\Enums\PlanKey;
use App\Enums\ResultTokenAccessOutcome;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\Guardian;
use App\Models\ResultCheckingPin;
use App\Models\ResultCheckingPinUsage;
use App\Models\ResultTokenAccessLog;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Notifications\ResultAvailableNotification;
use App\Services\ResultTokenIssuer;
use Database\Factories\ResultCheckingPinFactory;

/**
 * Result tokens.
 *
 * One token authorises one student's one result, and nothing else. That is not
 * a policy the interface enforces - it is what the token IS, because it is
 * bound to its student and its examination when it is created and an
 * examination carries the class, term and session.
 *
 * The tests that matter most here are the ones that try to get at a result the
 * token was not issued for: another term, another student, a guessed id in a
 * URL. See docs/RESULT-TOKENS.md.
 */
function schoolWithResult(string $term = 'first', string $session = '2026/2027', string $class = 'JSS 1'): array
{
    $school = School::factory()->create();
    $examination = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => $class,
        'term' => $term,
        'session' => $session,
    ]);
    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'max_score' => 100,
    ]);
    $student = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => $class,
        'is_active' => true,
    ]);
    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 80,
    ]);

    return [$school, $examination, $student];
}

function issueTokenFor(School $school, Student $student, Examination $examination): string
{
    $issuer = app(ResultTokenIssuer::class);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return $issuer->issue($school, $student, $examination, $admin)['plain'];
}

// -----------------------------------------------------------------------------
// One token, one student, one term

// -----------------------------------------------------------------------------

test('a valid token opens the result it was issued for', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $token])
        ->assertRedirect();
    $this->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $token])
        ->assertOk()
        ->assertSee($student->fullName());
});

test('a token for one term does not open another term', function () {
    [$school, $firstTerm, $student] = schoolWithResult('first');
    // Same student, same class, same session - only the term differs.
    $secondTerm = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => $student->class_name,
        'term' => 'second',
        'session' => '2026/2027',
    ]);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $secondTerm->id, 'max_score' => 100]);
    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 70,
    ]);
    $firstTermToken = issueTokenFor($school, $student, $firstTerm);
    // Redeeming it can only ever produce the first-term result, because that
    // is what the token points at. There is no way to ask it for the second.
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $firstTermToken])
        ->assertOk()
        // The shared report card identifies the result by term and session, so
        // that is what distinguishes one from the other on the page.
        ->assertSee('First Term')
        ->assertDontSee('Second Term');
});

test('each term needs its own token', function () {
    [$school, $firstTerm, $student] = schoolWithResult('first');
    $secondTerm = Examination::factory()->create([
        'school_id' => $school->id,
        'class_name' => $student->class_name,
        'term' => 'second',
        'session' => '2026/2027',
    ]);
    $tokenA = issueTokenFor($school, $student, $firstTerm);
    $tokenB = issueTokenFor($school, $student, $secondTerm);
    expect($tokenA)->not->toBe($tokenB);
    $a = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($tokenA))->first();
    $b = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($tokenB))->first();
    expect($a->examination_id)->toBe($firstTerm->id)
        ->and($b->examination_id)->toBe($secondTerm->id)
        ->and($a->bound_student_id)->toBe($student->id)
        ->and($b->bound_student_id)->toBe($student->id);
});

test('a parent with several children gets a separate token for each', function () {
    [$school, $examination, $childA] = schoolWithResult();
    $childB = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => $childA->class_name,
        'is_active' => true,
    ]);
    $tokenA = issueTokenFor($school, $childA, $examination);
    $tokenB = issueTokenFor($school, $childB, $examination);
    expect($tokenA)->not->toBe($tokenB);
    // Child A's token shows child A, never child B - no matter that the same
    // parent holds both and the results sit in the same examination.
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $childA, false);
    $this->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => $tokenA])
        ->assertOk()
        ->assertSee($childA->fullName())
        ->assertDontSee($childB->fullName());
});

// -----------------------------------------------------------------------------
// Refusals, all of which look identical from outside

// -----------------------------------------------------------------------------

test('a token that does not exist is refused', function () {
    [$school, , $student] = schoolWithResult();
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => 'EDU-AAAA-BBBB-CCCC-DDDD'])
        ->assertSessionHasErrors('code');
});

test('an invalid token reveals nothing about any student', function () {
    [$school, $examination, $student] = schoolWithResult();

    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);

    // from() so the redirect back lands on the token form, where the error
    // renders - without it a test request has no referer and back() goes to "/".
    $response = $this->from(route('check-result.confirm', $school))
        ->followingRedirects()
        ->post(route('check-result.verify', $school), ['code' => 'EDU-AAAA-BBBB-CCCC-DDDD']);
    // The pupil's name is on screen deliberately - step one put it there.
    // What a wrong token must still give away is the result itself.
    $response->assertDontSee('Report Card')
        ->assertDontSee('Position')
        ->assertSee('Invalid Result Token');
});

test('every kind of refusal gives exactly the same message', function () {
    [$school, $examination, $student] = schoolWithResult();
    $revoked = issueTokenFor($school, $student, $examination);
    ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($revoked))->update(['status' => 'revoked']);
    $suspended = issueTokenFor($school, $student, $examination);
    ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($suspended))->update(['status' => 'suspended']);
    $expired = issueTokenFor($school, $student, $examination);
    ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($expired))->update(['expires_at' => now()->subDay()]);
    // Distinguishing these would let someone learn which tokens are real.
    foreach ([$revoked, $suspended, $expired, 'EDU-ZZZZ-ZZZZ-ZZZZ-ZZZZ'] as $attempt) {
        // Step one: the token is only checked against a pupil already named.
        identifyForResultCheck($school, $student, false);
        $this->post(route('check-result.verify', $school), ['code' => $attempt])
            ->assertSessionHasErrors('code');
        expect(session('errors')->first('code'))->toContain('Invalid Result Token');
    }
});

test('a token whose result has no scores yet is refused', function () {
    $school = School::factory()->create();
    $examination = Examination::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    $token = issueTokenFor($school, $student, $examination);
    // Issued before the scores were entered, which a school may legitimately
    // do. There is nothing to show yet.
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $token])
        ->assertSessionHasErrors('code');
});

test('an unbound token from the old scratch-card model is refused', function () {
    [$school, $examination, $student] = schoolWithResult();
    ResultCheckingPin::factory()->unissued()->create(['school_id' => $school->id]);
    $plain = ResultCheckingPinFactory::$lastPlainToken;
    // An unbound token is exactly the general-purpose access code the rule
    // forbids, so it is refused rather than binding itself to whoever tries it.
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $plain])
        ->assertSessionHasErrors('code');
});

test('a token from one school does not work at another', function () {
    [$schoolA, $examinationA, $studentA] = schoolWithResult();
    [$schoolB, , $studentB] = schoolWithResult();
    $token = issueTokenFor($schoolA, $studentA, $examinationA);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($schoolB, $studentB, false);
    $this->post(route('check-result.verify', $schoolB), ['code' => $token])
        ->assertSessionHasErrors('code');
});

// -----------------------------------------------------------------------------
// The result page cannot be reached by editing a URL

// -----------------------------------------------------------------------------

test('the result page is addressed by uuid, not by a guessable id', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $token]);
    $usage = ResultCheckingPinUsage::latest('id')->first();
    // If this were the integer id, /result/123 could be walked to /result/124.
    expect(route('check-result.result', ['school' => $school, 'usage' => $usage]))
        ->toContain($usage->uuid)
        ->and($usage->uuid)->not->toBe((string) $usage->id);
});

test('a result page closes if its token is revoked afterwards', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $token]);
    $usage = ResultCheckingPinUsage::latest('id')->first();
    $this->get(route('check-result.result', ['school' => $school, 'usage' => $usage]))->assertOk();
    // The school revokes it - perhaps the link was shared around.
    $usage->pin->update(['status' => 'revoked']);
    $this->get(route('check-result.result', ['school' => $school, 'usage' => $usage]))->assertNotFound();
});

// -----------------------------------------------------------------------------
// Storage and generation

// -----------------------------------------------------------------------------

test('the token is never stored in readable form', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    $row = DB::table('result_checking_pins')->latest('id')->first();
    // The hash is a hash, and the stored copy is ciphertext - neither is the
    // token, so reading this table gets an attacker nothing.
    expect($row->token_hash)->toBe(hash('sha256', $token))
        ->and($row->token_hash)->not->toBe($token)
        ->and($row->token_encrypted)->not->toContain($token);
});

test('a token is not derived from anything about the student', function () {
    [$school, $examination, $student] = schoolWithResult();
    $tokens = collect(range(1, 5))->map(function () use ($school, $examination) {
        $other = Student::factory()->create([
            'school_id' => $school->id,
            'class_name' => $examination->class_name,
            'is_active' => true,
        ]);

        return issueTokenFor($school, $other, $examination);
    });
    // All different, and none contains the identifiers somebody looking at a
    // class register would already know.
    //
    // The first five characters ARE derived - from the session and the term,
    // which is deliberate and is the same for every student sitting that
    // examination. It is the remaining ten that have to be unguessable, so
    // that is where the comparison is made.
    expect($tokens->unique())->toHaveCount(5);
    foreach ($tokens as $token) {
        expect($token)->toHaveLength(ResultCheckingPin::TOKEN_LENGTH)
            ->and($token)->toStartWith('26271')
            ->and(substr($token, 5))->not->toContain((string) $student->id)
            ->and(substr($token, 5))->not->toContain($student->admission_number);
    }

    // And the random half really is the random half.
    expect($tokens->map(fn (string $token) => substr($token, 5))->unique())->toHaveCount(5);
});

test('a token can be redeemed only as many times as it allows', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    foreach (range(1, ResultTokenIssuer::DEFAULT_MAX_USES) as $ignored) {
        // Step one: the token is only checked against a pupil already named.
        identifyForResultCheck($school, $student, false);
        $this->post(route('check-result.verify', $school), ['code' => $token])->assertRedirect();
    }
    $this->post(route('check-result.verify', $school), ['code' => $token])
        ->assertSessionHasErrors('code');
});

// -----------------------------------------------------------------------------
// Issuing

// -----------------------------------------------------------------------------

test('a school cannot issue a token binding a student to another class result', function () {
    [$school, $examination] = schoolWithResult('first', '2026/2027', 'JSS 1');
    $otherClassStudent = Student::factory()->create([
        'school_id' => $school->id,
        'class_name' => 'SS 3',
        'is_active' => true,
    ]);
    // The binding is validated rather than trusted, whatever the request says.
    expect(fn () => issueTokenFor($school, $otherClassStudent, $examination))
        ->toThrow(LogicException::class);
});

test('a school cannot issue a token for another school student', function () {
    [$schoolA, $examinationA] = schoolWithResult();
    [$schoolB, , $studentB] = schoolWithResult();
    expect(fn () => issueTokenFor($schoolA, $studentB, $examinationA))
        ->toThrow(LogicException::class);
});

test('bulk issuing covers a class and skips students who already hold a token', function () {
    [$school, $examination, $student] = schoolWithResult();
    Student::factory()->count(3)->create([
        'school_id' => $school->id,
        'class_name' => $examination->class_name,
        'is_active' => true,
    ]);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    activateSchool($school, PlanKey::Basic);
    $issuer = app(ResultTokenIssuer::class);
    expect($issuer->issueForExamination($school, $examination, $admin))->toHaveCount(4);
    // Running it again issues nothing, so a parent is never left holding two
    // live tokens wondering which is real.
    expect($issuer->issueForExamination($school, $examination, $admin))->toHaveCount(0);
});

test('reissuing revokes the old token and produces a different one', function () {
    [$school, $examination, $student] = schoolWithResult();
    $original = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($original))->first();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $replacement = app(ResultTokenIssuer::class)->reissue($token, $admin)['plain'];
    expect($replacement)->not->toBe($original)
        ->and($token->fresh()->status->value)->toBe('revoked');
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $original])->assertSessionHasErrors('code');
    $this->post(route('check-result.verify', $school), ['code' => $replacement])->assertRedirect();
});

// -----------------------------------------------------------------------------
// Every attempt is logged

// -----------------------------------------------------------------------------

test('a successful redemption is logged', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $token]);
    $entry = ResultTokenAccessLog::latest('id')->first();
    expect($entry->outcome)->toBe(ResultTokenAccessOutcome::Succeeded)
        ->and($entry->student_id)->toBe($student->id)
        ->and($entry->examination_id)->toBe($examination->id)
        ->and($entry->school_id)->toBe($school->id)
        ->and($entry->occurred_at)->not->toBeNull()
        ->and($entry->ip_address)->not->toBeNull();
});

test('a failed attempt is logged too, without storing the token tried', function () {
    [$school, , $student] = schoolWithResult();
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => 'EDU-WRON-GWRO-NGWR-ONGX']);
    $entry = ResultTokenAccessLog::latest('id')->first();
    expect($entry->outcome)->toBe(ResultTokenAccessOutcome::NotFound)
        ->and($entry->student_id)->toBeNull()
        ->and($entry->token_hash_attempted)->toBe(hash('sha256', 'EDU-WRON-GWRO-NGWR-ONGX'))
        ->and($entry->token_hash_attempted)->not->toBe('EDU-WRON-GWRO-NGWR-ONGX');
});

test('guessing is rate limited per address, not per token tried', function () {
    [$school, , $student] = schoolWithResult();
    // Every guess is a different value. A limiter keyed on the token typed
    // would give each its own counter and never fire.
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);

    foreach (range(1, 9) as $i) {
        $this->post(route('check-result.verify', $school), ['code' => 'EDU-AAAA-BBBB-CCCC-'.str_pad((string) $i, 4, 'X')]);
    }
    $this->post(route('check-result.verify', $school), ['code' => 'EDU-AAAA-BBBB-CCCC-ZZZZ'])
        ->assertSessionHasErrors('code');
    expect(session('errors')->first('code'))->toContain('seconds');
    expect(ResultTokenAccessLog::where('outcome', ResultTokenAccessOutcome::RateLimited)->exists())->toBeTrue();
});

// -----------------------------------------------------------------------------
// Available on every plan

// -----------------------------------------------------------------------------

test('the token entry page works for a school with no website', function () {
    [$school] = schoolWithResult();
    $this->get(route('check-result.show', $school))
        ->assertOk()
        ->assertSee('School ID / Admission Number');
});

test('every plan can manage result tokens', function (PlanKey $plan) {
    $school = School::factory()->create();
    activateSchool($school, $plan);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    // Tokens are how a result reaches a parent safely, not a feature a school
    // upgrades to. They used to be gated to Basic.
    $this->actingAs($admin)->get(route('result-pins.index'))->assertOk();
})->with([
    'basic' => PlanKey::Basic,
    'standard' => PlanKey::Standard,
    'exclusive' => PlanKey::Exclusive,
]);

test('a school without an active subscription cannot manage tokens', function () {
    $school = School::factory()->create();
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    // Turned away by the school_activated gate before the token middleware is
    // even reached, so this is a redirect rather than a 403. Either way the
    // page is not served.
    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertRedirect();
});

test('a school admin cannot see or touch another school tokens', function () {
    [$schoolA, $examinationA, $studentA] = schoolWithResult();
    $tokenPlain = issueTokenFor($schoolA, $studentA, $examinationA);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($tokenPlain))->first();
    $schoolB = School::factory()->create();
    activateSchool($schoolB, PlanKey::Basic);
    $adminB = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $schoolB->id]);
    $this->actingAs($adminB)->post(route('result-pins.revoke', $token))->assertForbidden();
    $this->actingAs($adminB)->post(route('result-pins.reveal', $token))->assertForbidden();
    expect($token->fresh()->status->value)->toBe('active');
});

// -----------------------------------------------------------------------------
// Distributing results without distributing the result

// -----------------------------------------------------------------------------

test('the result notification announces the result without revealing it', function () {
    [$school, $examination, $student] = schoolWithResult();
    $guardian = Guardian::factory()->create(['school_id' => $school->id]);
    $guardian->students()->attach($student->id, ['relationship' => 'Mother']);
    $mail = (new ResultAvailableNotification($examination, $student))->toMail($guardian);
    // Assert on the mail's own lines rather than the rendered HTML: the layout
    // is full of incidental numbers (widths, colours, the year) that a naive
    // "does not contain 80" would trip over without telling us anything.
    $body = collect($mail->introLines)->merge($mail->outroLines)->implode(' ');
    // It says a result exists and how to reach it - and carries none of it. An
    // email gets forwarded; anything inside one is effectively public.
    expect($body)
        ->toContain('Result Token')
        ->not->toContain('80')
        ->not->toContain('%')
        ->and($mail->actionUrl)->toBe($school->fresh()->resultLinkUrl())
        ->and($mail->actionText)->toBe('Enter Result Token');
});

test('the notification points at the token prompt, not a portal', function () {
    // A Basic school has no portal at all, so "log in to your portal" was an
    // instruction a Basic parent could not follow.
    [$school, $examination, $student] = schoolWithResult();
    activateSchool($school, PlanKey::Basic);
    $mail = (new ResultAvailableNotification($examination, $student))->toMail($student);
    expect($mail->actionUrl)->toBe($school->fresh()->resultLinkUrl());
});

test('a student recipient is greeted by name', function () {
    [$school, $examination, $student] = schoolWithResult();
    // Students have no `name` column, only first and last, so reading one
    // blindly greeted every student with "Hello ,".
    $mail = (new ResultAvailableNotification($examination, $student))->toMail($student);
    expect($mail->greeting)->toBe('Hello '.$student->first_name.',');
});

// -----------------------------------------------------------------------------
// Super Admin oversight

// -----------------------------------------------------------------------------

test('the super admin sees token statistics and suspicious attempts', function () {
    [$school, $examination, $student] = schoolWithResult();
    $token = issueTokenFor($school, $student, $examination);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $token]);
    $this->post(route('check-result.verify', $school), ['code' => 'EDU-ZZZZ-ZZZZ-ZZZZ-ZZZZ']);
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->actingAs($superAdmin)
        ->get(route('super-admin.result-pins.index'))
        ->assertOk()
        ->assertSee($school->name)
        ->assertSee('Access attempts worth investigating');
});

test('the super admin can revoke any school token', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->actingAs($superAdmin)->post(route('super-admin.result-pins.revoke', $token))->assertRedirect();
    expect($token->fresh()->status->value)->toBe('revoked');
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $plain])->assertSessionHasErrors('code');
});

test('the super admin sets the platform defaults new tokens inherit', function () {
    [$school, $examination, $student] = schoolWithResult();
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->actingAs($superAdmin)
        ->put(route('super-admin.result-pins.settings'), [
            'result_token_max_uses' => 2,
            'result_token_expiry_days' => 30,
        ])
        ->assertRedirect();
    issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::latest('id')->first();
    expect($token->max_uses)->toBe(2)
        ->and($token->expires_at)->not->toBeNull()
        ->and($token->expires_at->isBetween(now()->addDays(29), now()->addDays(31)))->toBeTrue();
});

test('changing the platform defaults leaves tokens already issued alone', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);
    $this->actingAs($superAdmin)->put(route('super-admin.result-pins.settings'), [
        'result_token_max_uses' => 1,
        'result_token_expiry_days' => 1,
    ]);
    // A parent holding this one should not find it suddenly narrower than the
    // school told them it was.
    expect($token->fresh()->max_uses)->toBe(ResultTokenIssuer::DEFAULT_MAX_USES)
        ->and($token->fresh()->expires_at)->toBeNull();
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $plain])->assertRedirect();
});

test('an expired token is refused', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))
        ->update(['expires_at' => now()->subMinute()]);
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $plain])
        ->assertSessionHasErrors('code');
    expect(ResultTokenAccessLog::latest('id')->first()->outcome)
        ->toBe(ResultTokenAccessOutcome::Expired);
});

test('a school admin cannot reach the platform token settings', function () {
    $school = School::factory()->create();
    activateSchool($school, PlanKey::Basic);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $this->actingAs($admin)
        ->put(route('super-admin.result-pins.settings'), ['result_token_max_uses' => 50])
        ->assertForbidden();
});

test('a school sees an expired token as expired, not active', function () {
    [$school, $examination, $student] = schoolWithResult();
    activateSchool($school, PlanKey::Basic);
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $token->update(['expires_at' => now()->subDay()]);
    // Expiry is a date, so the stored status is still Active. Showing that
    // would have the school telling a parent to try a token that cannot work.
    expect($token->fresh()->displayStatusLabel())->toBe('Expired');
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
    $this->actingAs($admin)
        ->get(route('result-pins.index'))
        ->assertOk()
        ->assertSee('Expired');
});

// -----------------------------------------------------------------------------
// The school's own dashboard, over HTTP
//
// The issuer is exercised directly above, which skips the controller - and the
// controller is where a swapped identifier in a form post gets caught. These go
// through the routes a school actually uses.

// -----------------------------------------------------------------------------

function schoolAdminFor(School $school, PlanKey $plan = PlanKey::Basic): User
{
    activateSchool($school, $plan);

    return User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);
}

test('a school admin issues a token from the dashboard and is shown it once', function () {
    [$school, $examination, $student] = schoolWithResult();
    $admin = schoolAdminFor($school);
    $response = $this->actingAs($admin)->post(route('result-pins.store'), [
        'student_id' => $student->id,
        'examination_id' => $examination->id,
    ]);
    $response->assertRedirect();
    $token = ResultCheckingPin::latest('id')->first();
    expect($token->bound_student_id)->toBe($student->id)
        ->and($token->examination_id)->toBe($examination->id)
        ->and($token->status->value)->toBe('active');
    // The plain value is flashed for this one render and never stored.
    $plain = collect(session('issued_tokens'))->first()['token'];
    expect(ResultCheckingPin::hashToken($plain))->toBe($token->token_hash);
});

test('a school admin cannot issue a token for another school student', function () {
    [$schoolA, $examinationA] = schoolWithResult();
    [, , $studentB] = schoolWithResult();
    $admin = schoolAdminFor($schoolA);
    // Rejected by validation, before the issuer is ever reached - the rule
    // scopes the student to the acting school.
    $this->actingAs($admin)
        ->post(route('result-pins.store'), [
            'student_id' => $studentB->id,
            'examination_id' => $examinationA->id,
        ])
        ->assertSessionHasErrors('student_id');
    expect(ResultCheckingPin::count())->toBe(0);
});

test('a school admin cannot issue a token against another school examination', function () {
    [$schoolA, , $studentA] = schoolWithResult();
    [, $examinationB] = schoolWithResult();
    $admin = schoolAdminFor($schoolA);
    $this->actingAs($admin)
        ->post(route('result-pins.store'), [
            'student_id' => $studentA->id,
            'examination_id' => $examinationB->id,
        ])
        ->assertSessionHasErrors('examination_id');
    expect(ResultCheckingPin::count())->toBe(0);
});

test('a school admin bulk-issues tokens for a class from the dashboard', function () {
    [$school, $examination] = schoolWithResult();
    Student::factory()->count(2)->create([
        'school_id' => $school->id,
        'class_name' => $examination->class_name,
        'is_active' => true,
    ]);
    $admin = schoolAdminFor($school);
    $this->actingAs($admin)
        ->post(route('result-pins.store-bulk'), [
            // The class is chosen from the school's own list now, not implied
            // by whichever examination was picked.
            'class_name' => $examination->class_name,
            'examination_id' => $examination->id,
        ])
        ->assertRedirect();
    // Three students in the class, so three tokens - each bound to its own.
    expect(ResultCheckingPin::count())->toBe(3)
        ->and(ResultCheckingPin::distinct('bound_student_id')->count('bound_student_id'))->toBe(3)
        ->and(ResultCheckingPin::pluck('token_hash')->unique())->toHaveCount(3);
});

test('a school admin revokes its own token and it stops working', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $admin = schoolAdminFor($school);
    $this->actingAs($admin)->post(route('result-pins.revoke', $token))->assertRedirect();
    expect($token->fresh()->status->value)->toBe('revoked');
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $plain])->assertSessionHasErrors('code');
});

test('a school admin suspends a token and can restore it', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $admin = schoolAdminFor($school);
    $this->actingAs($admin)->post(route('result-pins.suspend', $token))->assertRedirect();
    expect($token->fresh()->status->value)->toBe('suspended');
    // Step one: the token is only checked against a pupil already named.
    identifyForResultCheck($school, $student, false);
    $this->post(route('check-result.verify', $school), ['code' => $plain])->assertSessionHasErrors('code');
    // Unlike revoking, this can be lifted - the parent keeps the token they
    // were given rather than needing a new one.
    $this->actingAs($admin)->post(route('result-pins.suspend', $token))->assertRedirect();
    expect($token->fresh()->status->value)->toBe('active');
    $this->post(route('check-result.verify', $school), ['code' => $plain])->assertRedirect();
});

test('a school admin can show a token again to hand it over a second time', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $admin = schoolAdminFor($school);
    $this->actingAs($admin)->post(route('result-pins.reveal', $token))->assertRedirect();
    // Recoverable through the encrypted column, and the same token as issued.
    expect(collect(session('issued_tokens'))->first()['token'])->toBe($plain);
});

test('revealing a token is written to the audit log', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $admin = schoolAdminFor($school);
    $this->actingAs($admin)->post(route('result-pins.reveal', $token));
    // Reading a live credential back out is exactly the action worth being
    // able to review afterwards.
    expect(AuditLog::where('action', 'result-token.revealed')->exists())->toBeTrue();
});

test('a school admin cannot reach the super admin token screens', function () {
    [$school, $examination, $student] = schoolWithResult();
    $plain = issueTokenFor($school, $student, $examination);
    $token = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->first();
    $admin = schoolAdminFor($school);
    $this->actingAs($admin)->get(route('super-admin.result-pins.index'))->assertForbidden();
    $this->actingAs($admin)->get(route('super-admin.result-pins.show', $school))->assertForbidden();
    $this->actingAs($admin)->post(route('super-admin.result-pins.revoke', $token))->assertForbidden();
    expect($token->fresh()->status->value)->toBe('active');
});
