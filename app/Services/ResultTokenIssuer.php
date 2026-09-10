<?php

namespace App\Services;

use App\Enums\ResultCheckingPinStatus;
use App\Models\Examination;
use App\Models\ResultCheckingPin;
use App\Models\School;
use App\Models\Setting;
use App\Models\Student;
use App\Models\User;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
use LogicException;

/**
 * Issues result tokens.
 *
 * Every token is bound to its student and its examination here, at creation.
 * That is the whole design: there is no later moment at which a token decides
 * what it is for, so there is no window in which it is a general-purpose code.
 *
 * The binding is also validated rather than trusted. A school user chooses a
 * student and an examination from their own dashboard, but the identifiers
 * arrive in an HTTP request and could be anything, so this class re-checks that
 * both belong to the issuing school and that the student's class matches the
 * examination's before it will create anything.
 */
class ResultTokenIssuer
{
    /**
     * How many times one token may be redeemed.
     *
     * More than one because a parent will reasonably look at a result more than
     * once, and small because a token that can be viewed without limit is a
     * token worth passing around. The Super Admin can raise or lower it for the
     * whole platform in settings; this is the fallback when nothing is set.
     */
    public const DEFAULT_MAX_USES = 5;

    /**
     * Platform settings, read lazily and kept for the life of this instance.
     */
    private ?Setting $settings = null;

    /**
     * Issue a token for one student and one examination.
     *
     * Returns the token model together with its plain text, which is the only
     * moment the plain value exists. After this it is recoverable solely
     * through the encrypted column.
     *
     * @return array{token: ResultCheckingPin, plain: string}
     */
    public function issue(
        School $school,
        Student $student,
        Examination $examination,
        User $issuedBy,
        ?int $maxUses = null,
    ): array {
        $this->assertBindingIsValid($school, $student, $examination);

        // No session or term is passed any more: a token encodes nothing about
        // what it opens. The examination it is bound to below is what decides
        // that, and always was.
        $plain = ResultCheckingPin::generatePlainToken();

        $token = DB::transaction(function () use ($school, $student, $examination, $issuedBy, $maxUses, &$plain) {
            // A collision is vanishingly unlikely at 69 bits, but "unlikely"
            // is not "impossible" and the column is unique, so retry rather
            // than fail the school's whole batch on a freak clash.
            while (ResultCheckingPin::where('token_hash', ResultCheckingPin::hashToken($plain))->exists()) {
                $plain = ResultCheckingPin::generatePlainToken();
            }

            return ResultCheckingPin::create([
                'school_id' => $school->id,
                'bound_student_id' => $student->id,
                'examination_id' => $examination->id,
                'token_hash' => ResultCheckingPin::hashToken($plain),
                'token_encrypted' => ResultCheckingPin::normaliseToken($plain),
                'status' => ResultCheckingPinStatus::Active,
                'max_uses' => $maxUses ?? $this->defaultMaxUses(),
                'uses_count' => 0,
                'generated_by' => $issuedBy->id,
                'issued_at' => now(),
                'expires_at' => $this->defaultExpiresAt(),
            ]);
        });

        return ['token' => $token, 'plain' => $plain];
    }

    /**
     * Issue a token for every student sitting an examination who does not
     * already hold a usable one.
     *
     * Skipping students who already have a live token matters: running the
     * bulk action twice should not leave a parent holding two valid tokens and
     * wondering which is real.
     *
     * @return Collection<int, array{token: ResultCheckingPin, plain: string, student: Student}>
     */
    public function issueForExamination(
        School $school,
        Examination $examination,
        User $issuedBy,
        ?int $maxUses = null,
    ): Collection {
        if ($examination->school_id !== $school->id) {
            throw new LogicException('That examination belongs to another school.');
        }

        $alreadyHolding = ResultCheckingPin::query()
            ->forSchool($school)
            ->where('examination_id', $examination->id)
            ->whereIn('status', [ResultCheckingPinStatus::Active, ResultCheckingPinStatus::Suspended])
            ->pluck('bound_student_id')
            ->filter()
            ->all();

        return $school->students()
            ->where('is_active', true)
            ->where('class_name', $examination->class_name)
            ->whereNotIn('id', $alreadyHolding)
            ->orderBy('last_name')
            ->get()
            ->map(fn (Student $student): array => [
                ...$this->issue($school, $student, $examination, $issuedBy, $maxUses),
                'student' => $student,
            ]);
    }

    /**
     * Replace a token with a fresh one for the same student and result.
     *
     * Used when a parent loses theirs. The old token is revoked rather than
     * edited, so the record of what was issued and when survives.
     *
     * @return array{token: ResultCheckingPin, plain: string}
     */
    public function reissue(ResultCheckingPin $token, User $issuedBy): array
    {
        if (! $token->isIssued()) {
            throw new LogicException('An unissued token has nothing to reissue against.');
        }

        return DB::transaction(function () use ($token, $issuedBy): array {
            $token->update(['status' => ResultCheckingPinStatus::Revoked]);

            return $this->issue(
                $token->school,
                $token->boundStudent,
                $token->examination,
                $issuedBy,
                $token->max_uses,
            );
        });
    }

    /**
     * Refuse to create a token that points somewhere it should not.
     *
     * Point 12 of the rule: generating a token must never let a school user
     * associate it with the wrong student or term, whatever the request says.
     */
    private function assertBindingIsValid(School $school, Student $student, Examination $examination): void
    {
        if ($student->school_id !== $school->id) {
            throw new LogicException('That student belongs to another school.');
        }

        if ($examination->school_id !== $school->id) {
            throw new LogicException('That examination belongs to another school.');
        }

        // An examination is for one class in one term, so a student in another
        // class has no result under it and a token pointing there would open
        // an empty page at best.
        if ($student->class_name !== $examination->class_name) {
            throw new LogicException(
                "That student is in {$student->class_name}, but the examination is for {$examination->class_name}."
            );
        }
    }

    /**
     * How many views a new token allows.
     *
     * The Super Admin sets this platform-wide; the constant is the fallback for
     * a fresh installation whose settings row has not been written yet.
     */
    private function defaultMaxUses(): int
    {
        return $this->settings()->result_token_max_uses ?: self::DEFAULT_MAX_USES;
    }

    /**
     * When a newly issued token stops working, or null if it never does.
     *
     * Resolved at issue rather than read at redemption, so shortening the
     * platform default never invalidates a token a parent already holds.
     */
    private function defaultExpiresAt(): ?CarbonInterface
    {
        $days = $this->settings()->result_token_expiry_days;

        return $days > 0 ? now()->addDays($days) : null;
    }

    /**
     * Read once per issuer instance, so issuing a whole class does not re-read
     * the settings row for every student.
     */
    private function settings(): Setting
    {
        return $this->settings ??= Setting::current();
    }
}
