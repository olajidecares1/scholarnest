<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\CbtTestAttemptFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtTestAttempt extends Model
{
    /** @use HasFactory<CbtTestAttemptFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'student_id',
        'cbt_test_id',
        'started_at',
        'expires_at',
        'submitted_at',
        'auto_submitted',
        'score',
        'total_questions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'started_at' => 'datetime',
            'expires_at' => 'datetime',
            'submitted_at' => 'datetime',
            'auto_submitted' => 'boolean',
            'score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<CbtTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(CbtTest::class, 'cbt_test_id');
    }

    /**
     * @return HasMany<CbtTestAttemptAnswer, $this>
     */
    public function answers(): HasMany
    {
        return $this->hasMany(CbtTestAttemptAnswer::class);
    }

    public function isSubmitted(): bool
    {
        return $this->submitted_at !== null;
    }

    public function isExpired(): bool
    {
        return ! $this->isSubmitted() && $this->expires_at !== null && $this->expires_at->isPast();
    }

    public function correctCount(): int
    {
        return $this->answers->where('is_correct', true)->count();
    }

    public function percentage(): float
    {
        if ($this->score !== null) {
            return (float) $this->score;
        }

        return $this->total_questions > 0
            ? round(($this->correctCount() / $this->total_questions) * 100, 1)
            : 0.0;
    }

    public function passed(): bool
    {
        return $this->percentage() >= $this->test->pass_mark;
    }
}
