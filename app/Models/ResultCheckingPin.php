<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Enums\ResultCheckingPinStatus;
use App\Enums\ResultTokenAccessOutcome;
use App\Support\HasUuidRouteKey;
use Database\Factories\ResultCheckingPinFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * A result token: authorisation to view ONE student's result for ONE
 * examination, and nothing else.
 *
 * A token is not a school access code. It is bound to its student and its
 * examination at the moment it is issued, and since an examination carries the
 * class, term and session, that binding is what makes "one token, one student,
 * one term" true rather than merely intended. A token issued for John Doe's
 * First Term cannot open his Second Term, his sibling's result, or anyone
 * else's - not because the interface hides those, but because the token simply
 * does not point at them.
 *
 * The token itself is never stored. `token_hash` is what verification looks up;
 * `token_encrypted` exists only so a school can redisplay a token it has to
 * hand to a parent again.
 */
class ResultCheckingPin extends Model
{
    /** @use HasFactory<ResultCheckingPinFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * Excludes visually ambiguous characters (0/O, 1/I) so a printed or
     * read-aloud token cannot be misheard into a different valid one.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * Exactly fifteen characters: session, term, then randomness.
     *
     *   2526  3  QK7M92XP4Q      ->  25263QK7M92XP4Q
     *   ----  -  ----------
     *   year  |  10 random characters
     *         term (1, 2 or 3)
     *
     * The leading five are not a secret and are not treated as one - they are
     * there so a school looking at a slip of paper can tell at a glance which
     * term it belongs to. The security is the ten random characters: 32^10 is
     * about 50 bits, which is far past guessable against a rate limiter, and
     * a token still only opens the one result it was issued against.
     *
     * The previous format was EDU-7K92-XP41-8ZQD-M3VH - 23 characters, with
     * nothing in it to say what it was for.
     */
    private const YEAR_LENGTH = 4;

    private const TERM_LENGTH = 1;

    public const TOKEN_LENGTH = 15;

    private const RANDOM_LENGTH = self::TOKEN_LENGTH - self::YEAR_LENGTH - self::TERM_LENGTH;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'examination_id',
        'token_hash',
        'token_encrypted',
        'status',
        'max_uses',
        'uses_count',
        'bound_student_id',
        'generated_by',
        'issued_at',
        'expires_at',
        'last_accessed_at',
    ];

    /**
     * token_hash and token_encrypted are hidden so a token cannot escape
     * through a JSON response or a debug dump.
     *
     * @var list<string>
     */
    protected $hidden = [
        'token_hash',
        'token_encrypted',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResultCheckingPinStatus::class,
            'max_uses' => 'integer',
            'uses_count' => 'integer',
            'issued_at' => 'datetime',
            'expires_at' => 'datetime',
            'last_accessed_at' => 'datetime',

            // Decrypted on read, encrypted on write, transparently. The plain
            // value never reaches the database.
            'token_encrypted' => 'encrypted',
        ];
    }

    // -------------------------------------------------------------------------
    // Relationships
    // -------------------------------------------------------------------------

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * The one student this token may ever be used for.
     *
     * @return BelongsTo<Student, $this>
     */
    public function boundStudent(): BelongsTo
    {
        return $this->belongsTo(Student::class, 'bound_student_id');
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return HasMany<ResultCheckingPinUsage, $this>
     */
    public function usages(): HasMany
    {
        return $this->hasMany(ResultCheckingPinUsage::class);
    }

    /**
     * @return HasMany<ResultTokenAccessLog, $this>
     */
    public function accessLogs(): HasMany
    {
        return $this->hasMany(ResultTokenAccessLog::class);
    }

    // -------------------------------------------------------------------------
    // State
    // -------------------------------------------------------------------------

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->uses_count);
    }

    public function hasExpired(): bool
    {
        return $this->expires_at !== null && $this->expires_at->isPast();
    }

    /**
     * The status to show a school, which is not quite the stored one.
     *
     * Expiry is a date rather than a state, so a token past its expiry is
     * still stored as Active. Printing "Active" beside a token that will be
     * refused is the kind of small lie that ends with a school telling a
     * parent to just try again.
     */
    public function displayStatusLabel(): string
    {
        return $this->status->allowsAccess() && $this->hasExpired()
            ? 'Expired'
            : $this->status->label();
    }

    public function displayStatusColour(): string
    {
        return $this->status->allowsAccess() && $this->hasExpired()
            ? 'gray'
            : $this->status->badgeColour();
    }

    /**
     * Has this token been bound to a student and a result?
     *
     * Tokens created before this system existed were deliberately unbound, and
     * an unbound token is exactly the general-purpose access code the rule
     * forbids. They are refused rather than quietly binding on first use.
     */
    public function isIssued(): bool
    {
        return $this->issued_at !== null
            && $this->bound_student_id !== null
            && $this->examination_id !== null;
    }

    /**
     * Why this token cannot be redeemed, or null if it can.
     *
     * Order matters only for what gets logged; the person redeeming always sees
     * the same generic message whichever of these applies.
     */
    public function accessRefusalReason(): ?ResultTokenAccessOutcome
    {
        if (! $this->isIssued()) {
            return ResultTokenAccessOutcome::NotIssued;
        }

        if ($this->status === ResultCheckingPinStatus::Revoked) {
            return ResultTokenAccessOutcome::Revoked;
        }

        if ($this->status === ResultCheckingPinStatus::Suspended) {
            return ResultTokenAccessOutcome::Suspended;
        }

        if ($this->hasExpired()) {
            return ResultTokenAccessOutcome::Expired;
        }

        if ($this->status === ResultCheckingPinStatus::Exhausted || $this->remainingUses() < 1) {
            return ResultTokenAccessOutcome::Exhausted;
        }

        return null;
    }

    // -------------------------------------------------------------------------
    // Query scopes
    // -------------------------------------------------------------------------

    /**
     * @param  Builder<ResultCheckingPin>  $query
     */
    public function scopeForSchool(Builder $query, School|int $school): void
    {
        $query->where('school_id', $school instanceof School ? $school->id : $school);
    }

    /**
     * @param  Builder<ResultCheckingPin>  $query
     */
    public function scopeIssued(Builder $query): void
    {
        $query->whereNotNull('issued_at')
            ->whereNotNull('bound_student_id')
            ->whereNotNull('examination_id');
    }

    // -------------------------------------------------------------------------
    // Token generation and lookup
    // -------------------------------------------------------------------------

    /**
     * A fresh token, in plain text.
     *
     * The caller must hand this straight to the school and then forget it - it
     * is only recoverable afterwards through the encrypted column.
     *
     * random_int is used rather than rand or mt_rand because it draws from the
     * operating system's cryptographic source. A token derived from anything
     * about the student - their id, admission number, name or date of birth -
     * would be guessable by whoever knows those things, which for a school
     * record is a great many people.
     */
    public static function generatePlainToken(string $session, ExamTerm $term): string
    {
        $alphabetLength = strlen(self::CODE_ALPHABET);

        $random = collect(range(1, self::RANDOM_LENGTH))
            ->map(fn (): string => self::CODE_ALPHABET[random_int(0, $alphabetLength - 1)])
            ->implode('');

        return self::sessionSegment($session).self::termSegment($term).$random;
    }

    /**
     * "2025/2026" -> "2526". Four digits, whatever shape the school writes its
     * sessions in, so the token length never depends on that.
     *
     * Falls back to the digits it can find, and pads, because a session is a
     * free-text field on the examination and a school may have typed anything
     * into it. A token must still come out fifteen characters long.
     */
    private static function sessionSegment(string $session): string
    {
        preg_match_all('/\d{4}/', $session, $matches);

        $years = collect($matches[0])->map(fn (string $year) => substr($year, -2));

        if ($years->count() >= 2) {
            return $years->first().$years->get(1);
        }

        $digits = preg_replace('/\D/', '', $session) ?? '';

        return str_pad(substr($digits, 0, self::YEAR_LENGTH), self::YEAR_LENGTH, '0', STR_PAD_LEFT);
    }

    private static function termSegment(ExamTerm $term): string
    {
        return match ($term) {
            ExamTerm::First => '1',
            ExamTerm::Second => '2',
            ExamTerm::Third => '3',
        };
    }

    /**
     * The lookup value for a plain token.
     *
     * Normalised first, so a parent typing lower case or padding it with spaces
     * still matches. SHA-256 rather than a password hash: the input is already
     * 80 bits of randomness, so there is nothing to slow an attacker down
     * against, and a deterministic hash keeps verification one indexed lookup
     * instead of a scan of every token in the table.
     */
    public static function hashToken(string $plain): string
    {
        return hash('sha256', self::normaliseToken($plain));
    }

    /**
     * Tidies up what someone typed: trims, upper-cases, and strips the spaces
     * people insert around the hyphens when reading a token off paper.
     */
    public static function normaliseToken(string $plain): string
    {
        return strtoupper(preg_replace('/\s+/', '', trim($plain)) ?? '');
    }

    /**
     * The plain token, for redisplay to the school that issued it.
     */
    public function plainToken(): ?string
    {
        return $this->token_encrypted;
    }
}
