<?php

namespace App\Models;

use App\Enums\CbtSubjectCategory;
use App\Support\HasUuidRouteKey;
use Database\Factories\CbtSubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtSubject extends Model
{
    /** @use HasFactory<CbtSubjectFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'category',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => CbtSubjectCategory::class,
        ];
    }

    /**
     * @return BelongsToMany<CbtExamBody, $this>
     */
    public function examBodies(): BelongsToMany
    {
        return $this->belongsToMany(CbtExamBody::class, 'cbt_exam_body_subject');
    }

    /**
     * @return HasMany<CbtExam, $this>
     */
    public function exams(): HasMany
    {
        return $this->hasMany(CbtExam::class);
    }
}
