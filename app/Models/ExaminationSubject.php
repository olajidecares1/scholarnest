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
}
