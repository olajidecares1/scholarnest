<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

/**
 * One run of the bulk exam-token generator.
 *
 * The tokens it created point back at it, so "print this batch" prints the
 * students the School Admin picked and the tokens they were given, never a
 * re-derived list that could have changed since.
 */
class ResultTokenBatch extends Model
{
    use HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'generated_by',
        'session',
        'term',
        'token_count',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => ExamTerm::class,
            'token_count' => 'integer',
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
     * @return BelongsTo<User, $this>
     */
    public function generatedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'generated_by');
    }

    /**
     * @return HasMany<ResultCheckingPin, $this>
     */
    public function tokens(): HasMany
    {
        return $this->hasMany(ResultCheckingPin::class, 'batch_id');
    }

    /**
     * @param  Builder<ResultTokenBatch>  $query
     * @return Builder<ResultTokenBatch>
     */
    public function scopeForSchool(Builder $query, School $school): Builder
    {
        return $query->where('school_id', $school->id);
    }
}
