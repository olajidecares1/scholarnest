<?php

namespace App\Models;

use App\Enums\ResultCheckingPinStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\ResultCheckingPinFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ResultCheckingPin extends Model
{
    /** @use HasFactory<ResultCheckingPinFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * Excludes visually ambiguous characters (0/O, 1/I) so a printed or
     * read-aloud PIN can't be misheard/mistyped into a different valid code.
     */
    private const CODE_ALPHABET = 'ABCDEFGHJKLMNPQRSTUVWXYZ23456789';

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'examination_id',
        'code',
        'status',
        'max_uses',
        'uses_count',
        'bound_student_id',
        'generated_by',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => ResultCheckingPinStatus::class,
            'max_uses' => 'integer',
            'uses_count' => 'integer',
        ];
    }

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

    public function remainingUses(): int
    {
        return max(0, $this->max_uses - $this->uses_count);
    }

    /**
     * Generates a fresh, unused, unambiguous "XXXX-XXXX-XXXX" code - retried
     * on the rare unique-constraint collision rather than assumed unique,
     * since random generation can't otherwise guarantee it.
     */
    public static function generateUniqueCode(): string
    {
        do {
            $code = collect(range(1, 3))
                ->map(fn () => collect(range(1, 4))->map(fn () => self::CODE_ALPHABET[random_int(0, strlen(self::CODE_ALPHABET) - 1)])->implode(''))
                ->implode('-');
        } while (self::where('code', $code)->exists());

        return $code;
    }
}
