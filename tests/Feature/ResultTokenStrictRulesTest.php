<?php

use App\Enums\ExamTerm;
use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultTokenIssuer;
use App\Services\ResultTokenVerifier;
use Illuminate\Http\Request;

/**
 * The rules a result token has to obey, asserted rather than assumed.
 *
 * Most of these were already true. They are written down here because they are
 * the kind of thing that quietly stops being true - a token that opens the
 * wrong term, or a plan that gains a portal it was never sold - and nothing
 * else in the suite states them as rules in one place.
 */
function strictTokenSchool(PlanKey $plan = PlanKey::Standard): array
{
    $school = activateSchool(School::factory()->create(['current_session' => '2026/2027']), $plan);
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return [$school->fresh(), $admin];
}

function strictExaminationFor(School $school, string $session, ExamTerm $term, string $class = 'JSS 1'): Examination
{
    return Examination::factory()->create([
        'school_id' => $school->id,
        'session' => $session,
        'term' => $term,
        'class_name' => $class,
    ]);
}

/**
 * Put a mark against this student in this examination.
 *
 * The verifier refuses a token whose result is not there yet - correctly, since
 * opening an empty result sheet teaches a parent nothing - so any test that
 * expects a token to WORK has to record one first.
 */
function strictScoreFor(Examination $examination, Student $student): void
{
    $subject = ExaminationSubject::factory()->create([
        'examination_id' => $examination->id,
        'max_score' => 100,
    ]);

    ExaminationScore::factory()->create([
        'examination_subject_id' => $subject->id,
        'student_id' => $student->id,
        'score' => 72,
    ]);
}

// -----------------------------------------------------------------------------
// 1. The academic year comes from School Settings
// -----------------------------------------------------------------------------

describe('the academic year on the generation page', function () {
    test('defaults to the year set in School Settings', function () {
        [$school, $admin] = strictTokenSchool();

        // An examination in an OLDER session exists. The page used to default
        // to the newest examination's session, so a school that had moved on
        // in Settings was offered the year its last examination was filed
        // under - not the year it is actually in.
        strictExaminationFor($school, '2024/2025', ExamTerm::First);

        $this->actingAs($admin)
            ->get(route('result-pins.index'))
            ->assertOk()
            ->assertSee('2026/2027');
    });

    test('follows Settings when the year is changed there', function () {
        [$school, $admin] = strictTokenSchool();

        $school->update(['current_session' => '2027/2028']);

        // Read on every request, never cached and never hard-coded.
        $this->actingAs($admin)
            ->get(route('result-pins.index'))
            ->assertOk()
            ->assertSee('2027/2028');
    });

    test('previous sessions stay selectable, so historical tokens can be issued', function () {
        [$school, $admin] = strictTokenSchool();

        strictExaminationFor($school, '2024/2025', ExamTerm::Third);
        strictExaminationFor($school, '2025/2026', ExamTerm::First);

        $this->actingAs($admin)
            ->get(route('result-pins.index'))
            ->assertOk()
            ->assertSee('2024/2025')
            ->assertSee('2025/2026')
            ->assertSee('2026/2027');
    });
});

// -----------------------------------------------------------------------------
// 4 and 6. A token opens one examination, and nothing near it
// -----------------------------------------------------------------------------

describe('a token is valid for its own session and term only', function () {
    /**
     * Issue a token against one examination, then try it against another.
     */
    function strictTokenFor(School $school, Student $student, Examination $exam, User $admin): string
    {
        return app(ResultTokenIssuer::class)->issue($school, $student, $exam, $admin)['plain'];
    }

    test('the same session but a different term is refused', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

        $first = strictExaminationFor($school, '2025/2026', ExamTerm::First);
        $second = strictExaminationFor($school, '2025/2026', ExamTerm::Second);

        $plain = strictTokenFor($school, $student, $first, $admin);

        $verifier = app(ResultTokenVerifier::class);

        // Refused against Second Term...
        expect($verifier->verify($school, $plain, Request::create('/'), $student, $second))->toBeNull();
    });

    test('the same term but a different session is refused', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);

        $thisYear = strictExaminationFor($school, '2025/2026', ExamTerm::First);
        $nextYear = strictExaminationFor($school, '2026/2027', ExamTerm::First);

        $plain = strictTokenFor($school, $student, $thisYear, $admin);

        expect(app(ResultTokenVerifier::class)->verify($school, $plain, Request::create('/'), $student, $nextYear))
            ->toBeNull();
    });

    test('another school cannot use it, even holding the exact string', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
        $exam = strictExaminationFor($school, '2025/2026', ExamTerm::First);

        $plain = strictTokenFor($school, $student, $exam, $admin);

        [$otherSchool] = strictTokenSchool();

        expect(app(ResultTokenVerifier::class)->verify($otherSchool, $plain, Request::create('/')))
            ->toBeNull();
    });

    test('the validity comes from the row, not from the string', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
        $exam = strictExaminationFor($school, '2025/2026', ExamTerm::Third);

        $plain = strictTokenFor($school, $student, $exam, $admin);

        // Nothing in the twelve characters says "2025/2026" or "Third", and
        // nothing needs to: the stored row points at one examination, and an
        // examination IS one class in one term of one session.
        $stored = ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->sole();

        expect($stored->examination_id)->toBe($exam->id)
            ->and($stored->school_id)->toBe($school->id)
            ->and($stored->bound_student_id)->toBe($student->id)
            ->and($plain)->not->toContain('2025')
            ->and($plain)->not->toContain('2526');
    });
});

// -----------------------------------------------------------------------------
// 2. Which plans check results how
// -----------------------------------------------------------------------------

describe('result checking by plan', function () {
    test('every plan has the public result URL', function (PlanKey $plan) {
        [$school] = strictTokenSchool($plan);

        // The address a parent is given. Basic's only route to a result, and
        // still available to Standard and Exclusive.
        expect($school->resultLinkUrl())->toContain('/'.$school->result_link_slug.'/result');

        $this->get($school->resultLinkUrl())->assertOk();
    })->with([
        'basic' => PlanKey::Basic,
        'standard' => PlanKey::Standard,
        'exclusive' => PlanKey::Exclusive,
    ]);

    test('every plan can issue tokens', function (PlanKey $plan) {
        [$school, $admin] = strictTokenSchool($plan);

        $this->actingAs($admin)->get(route('result-pins.index'))->assertOk();
    })->with([
        'basic' => PlanKey::Basic,
        'standard' => PlanKey::Standard,
        'exclusive' => PlanKey::Exclusive,
    ]);

    test('Basic has no pupil or parent portal to check results in', function () {
        [$school] = strictTokenSchool(PlanKey::Basic);

        // The hub is the honest statement of it: no pupil or parent door, and
        // the token flow offered in their place.
        $this->get(route('portal.index', $school))
            ->assertOk()
            ->assertSee('Check Result')
            ->assertDontSee('Parent / Guardian');
    });

    test('Standard and Exclusive do', function (PlanKey $plan) {
        [$school] = strictTokenSchool($plan);

        $this->get(route('portal.index', $school))
            ->assertOk()
            ->assertSee('Student')
            ->assertSee('Parent / Guardian');
    })->with([
        'standard' => PlanKey::Standard,
        'exclusive' => PlanKey::Exclusive,
    ]);
});

// -----------------------------------------------------------------------------
// Tokens printed before the format changed
// -----------------------------------------------------------------------------

describe('the change of format does not strand a token already in a parent\'s hand', function () {
    test('a token issued under the old upper-case format still opens its result', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
        $exam = strictExaminationFor($school, '2025/2026', ExamTerm::First);
        strictScoreFor($exam, $student);

        // Exactly as the old issuer stored it: hashed from the upper-cased
        // form, because the alphabet had no lower case in it.
        $old = '25261QK7M92XP4Q';

        app(ResultTokenIssuer::class)->issue($school, $student, $exam, $admin);

        ResultCheckingPin::where('school_id', $school->id)->update([
            'token_hash' => ResultCheckingPin::legacyHashToken($old),
            'token_encrypted' => $old,
        ]);

        // A parent typing what is printed on their slip must still get in.
        expect(app(ResultTokenVerifier::class)->verify($school, $old, Request::create('/'), $student, $exam))
            ->not->toBeNull();
    });

    test('a new token is case sensitive, so the fallback cannot loosen it', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
        $exam = strictExaminationFor($school, '2025/2026', ExamTerm::First);
        strictScoreFor($exam, $student);

        $plain = app(ResultTokenIssuer::class)->issue($school, $student, $exam, $admin)['plain'];

        // The legacy path is tried only after an exact miss, and it hashes the
        // UPPER-CASED input - so a current mixed-case token typed in the wrong
        // case matches neither. Without that ordering, the fallback would have
        // quietly made every new token case-insensitive and thrown away a
        // chunk of its keyspace.
        expect(app(ResultTokenVerifier::class)->verify($school, strtoupper($plain), Request::create('/'), $student, $exam))
            ->toBeNull();

        // And typed correctly, it works.
        expect(app(ResultTokenVerifier::class)->verify($school, $plain, Request::create('/'), $student, $exam))
            ->not->toBeNull();
    });

    test('spaces someone types while reading it off paper are forgiven', function () {
        [$school, $admin] = strictTokenSchool();
        $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1']);
        $exam = strictExaminationFor($school, '2025/2026', ExamTerm::First);
        strictScoreFor($exam, $student);

        $plain = app(ResultTokenIssuer::class)->issue($school, $student, $exam, $admin)['plain'];

        $spaced = '  '.substr($plain, 0, 4).' '.substr($plain, 4, 4).' '.substr($plain, 8).'  ';

        expect(app(ResultTokenVerifier::class)->verify($school, $spaced, Request::create('/'), $student, $exam))
            ->not->toBeNull();
    });
});
