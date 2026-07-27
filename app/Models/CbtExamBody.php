<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\CbtExamBodyFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasManyThrough;

class CbtExamBody extends Model
{
    /** @use HasFactory<CbtExamBodyFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'code',
        'description',
    ];

    /**
     * @return BelongsToMany<CbtSubject, $this>
     */
    public function subjects(): BelongsToMany
    {
        return $this->belongsToMany(CbtSubject::class, 'cbt_exam_body_subject');
    }

    /**
     * @return HasMany<CbtExam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(CbtExam::class);
    }

    /**
     * @return HasManyThrough<CbtQuestion, CbtExam, $this>
     */
    public function questions(): HasManyThrough
    {
        return $this->hasManyThrough(CbtQuestion::class, CbtExam::class);
    }
}
