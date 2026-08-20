<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Support\HasUuidRouteKey;
use Database\Factories\ExaminationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Examination extends Model
{
    /** @use HasFactory<ExaminationFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'class_name',
        'term',
        'session',
        'exam_date',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => ExamTerm::class,
            'exam_date' => 'date:Y-m-d',
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
     * @return HasMany<ExaminationSubject, $this>
     */
    public function subjects(): HasMany
    {
        return $this->hasMany(ExaminationSubject::class);
    }

    /**
     * @return HasMany<ResultCheckingPin, $this>
     */
    public function resultCheckingPins(): HasMany
    {
        return $this->hasMany(ResultCheckingPin::class);
    }
}
