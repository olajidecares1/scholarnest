<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Services\DefaultClassSubjects;
use App\Support\HasUuidRouteKey;
use Database\Factories\ExaminationFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;

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
     * This examination's subjects in the order they belong on the result
     * sheet: the class's default subjects first in their printed order
     * (config/subjects.php), then any others A to Z.
     *
     * @param  Collection<int, ExaminationSubject>|null  $subjects  defaults to the loaded subjects
     * @return Collection<int, ExaminationSubject>
     */
    public function subjectsInResultSheetOrder(?Collection $subjects = null): Collection
    {
        return DefaultClassSubjects::sortForClass(
            $subjects ?? $this->subjects,
            (int) $this->school_id,
            (string) $this->class_name,
            fn ($subject) => $subject->name,
        );
    }

    /**
     * @return HasMany<ResultCheckingPin, $this>
     */
    public function resultCheckingPins(): HasMany
    {
        return $this->hasMany(ResultCheckingPin::class);
    }

    /**
     * The per-student report rows, where the two remarks live.
     *
     * @return HasMany<ExaminationReport, $this>
     */
    public function reports(): HasMany
    {
        return $this->hasMany(ExaminationReport::class);
    }

    /**
     * The published cards this examination produced.
     *
     * @return HasMany<RepositoryResult, $this>
     */
    public function repositoryResults(): HasMany
    {
        return $this->hasMany(RepositoryResult::class);
    }
}
