<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ExaminationSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class ExaminationSubject extends Model
{
    /** @use HasFactory<ExaminationSubjectFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'examination_id',
        'name',
        'max_score',
    ];

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * @return HasMany<ExaminationScore, $this>
     */
    public function scores(): HasMany
    {
        return $this->hasMany(ExaminationScore::class);
    }

    /**
     * The Test component's max score, derived from this subject's overall
     * max_score rather than stored separately - keeps the mandatory 40/60
     * Test/Exam split consistent even for legacy subjects whose max_score
     * isn't exactly 100, without touching any already-saved scores.
     */
    public function testMaxScore(): int
    {
        return (int) round($this->max_score * 0.4);
    }

    public function examMaxScore(): int
    {
        return $this->max_score - $this->testMaxScore();
    }
}
