<?php

namespace App\Models;

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
     * Upper case, lower case and digits - with the six characters nobody can
     * reliably tell apart left out.
     *
     * 0/O/o and 1/l/I are the pairs that get misread off a printed slip and
     * misheard down a telephone, and a parent who mistypes one has spent an
     * attempt against the rate limiter for nothing. Dropping them leaves 56
     * characters and 56^12 - about 69 bits - which is far past guessable.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZabcdefghijkmnpqrstuvwxyz23456789';

    /**
     * Exactly twelve characters, and every one of them random.
     *
     *   aB7xQ2mP9kL4
     *
     * NOTHING IS ENCODED IN IT. The previous format spent its first five
     * characters on the session and the term - 25263QK7M92XP4Q - so a token
     * announced on sight which term it belonged to, and anyone holding two
     * tokens could read off how the scheme worked.
     *
     * Removing that costs nothing, because the token never decided what it
     * opened in the first place: validity comes from the row, which is bound
     * to one examination, and an examination IS one class in one term of one
     * session. A First Term token cannot open Second Term because it points
     * at a different examination - not because of anything in the string.
     * See ResultTokenVerifier.
     */
    public const TOKEN_LENGTH = 12;

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
    public static function generatePlainToken(): string
    {
        $alphabetLength = strlen(self::CODE_ALPHABET);

        do {
            $token = '';

            for ($i = 0; $i < self::TOKEN_LENGTH; $i++) {
                $token .= self::CODE_ALPHABET[random_int(0, $alphabetLength - 1)];
            }

            // Drawn again until all three character classes are present, which
            // is what the format promises. Rejecting is the correct way round:
            // placing one of each at fixed positions and shuffling the rest
            // would make those positions predictable, which is the one thing a
            // token must not be. A draw of twelve misses a class about once in
            // every few thousand attempts, so this loops almost never.
        } while (! self::hasEveryCharacterClass($token));

        return $token;
    }

    /**
     * Upper case, lower case and a digit - all three, as the format requires.
     */
    private static function hasEveryCharacterClass(string $token): bool
    {
        return preg_match('/[A-Z]/', $token) === 1
            && preg_match('/[a-z]/', $token) === 1
            && preg_match('/\d/', $token) === 1;
    }

    // sessionSegment() and termSegment() are gone with the format they built.
    // A token no longer carries the session or the term, so there is nothing
    // to derive from either.

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
     * Tidies up what someone typed: trims and strips the spaces people insert
     * when reading a token off paper.
     *
     * IT NO LONGER UPPER-CASES, and it cannot. The token alphabet is mixed
     * case now, so "aB7xQ2mP9kL4" and "AB7XQ2MP9KL4" are different tokens -
     * upper-casing would have stored a token nobody was ever given and
     * hashed every attempt to the wrong value.
     *
     * Tokens issued under the old upper-case-only format still verify: see
     * legacyHashToken(), which the verifier falls back to so a parent holding
     * a printed token from last term is not turned away.
     */
    public static function normaliseToken(string $plain): string
    {
        return preg_replace('/\s+/', '', trim($plain)) ?? '';
    }

    /**
     * The hash an OLD token would have had, when the format was upper case
     * only and what somebody typed was upper-cased before hashing.
     *
     * Only ever consulted after the exact hash misses. Every token issued
     * from now on is mixed case and matches exactly.
     */
    public static function legacyHashToken(string $plain): string
    {
        return hash('sha256', strtoupper(self::normaliseToken($plain)));
    }

    /**
     * The plain token, for redisplay to the school that issued it.
     */
    public function plainToken(): ?string
    {
        return $this->token_encrypted;
    }
}
