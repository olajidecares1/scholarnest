<?php

namespace App\Models;

use App\Enums\ExamTerm;
use App\Support\HasUuidRouteKey;
use Database\Factories\RepositoryResultFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One published report card, as the school approved it.
 *
 * The payload is the whole card written down, subjects, marks, totals,
 * grades, remarks, attendance, position, rather than a pointer back to the
 * scores table. That is the point of the repository: a parent reads what the
 * school signed off, and a mark corrected afterwards reaches them when
 * somebody pushes it again, not the moment it is typed.
 *
 * Keyed by school + student + class + session + term, so a second push
 * updates rather than duplicates. See the migration.
 */
class RepositoryResult extends Model
{
    /** @use HasFactory<RepositoryResultFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'examination_id',
        'class_name',
        'session',
        'term',
        'payload',
        'source_fingerprint',
        'pushed_by_type',
        'pushed_by_id',
        'pushed_by_name',
        'pushed_at',
        'version',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'payload' => 'array',
            'term' => ExamTerm::class,
            'pushed_at' => 'datetime',
            'version' => 'integer',
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
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * The School Admin or member of staff who published it.
     *
     * @return MorphTo<Model, $this>
     */
    public function pushedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * Confine a query to one school.
     *
     * Every read of this table goes through here. The repository holds
     * children's report cards, and "which school is asking" is not a detail
     * to be remembered at each call site.
     *
     * @param  Builder<RepositoryResult>  $query
     * @return Builder<RepositoryResult>
     */
    public function scopeForSchool(Builder $query, School $school): Builder
    {
        // Qualified, because the repository page joins students to sort by
        // name and both tables have a school_id. An unqualified column here is
        // an ambiguous-column error at best, and at worst the wrong filter.
        return $query->where($this->qualifyColumn('school_id'), $school->id);
    }

    /**
     * @param  Builder<RepositoryResult>  $query
     * @return Builder<RepositoryResult>
     */
    public function scopeForTerm(Builder $query, string $className, string $session, ExamTerm $term): Builder
    {
        return $query->where($this->qualifyColumn('class_name'), $className)
            ->where($this->qualifyColumn('session'), $session)
            ->where($this->qualifyColumn('term'), $term->value);
    }

    /**
     * Has this been corrected since it was published?
     *
     * True when the marks or remarks behind the card no longer hash to what
     * they hashed to at the push. The stored card is still what a parent sees,
     * deliberately, and this is how the school is told there is a newer
     * version worth pushing.
     */
    public function isStaleAgainst(?string $currentFingerprint): bool
    {
        return $currentFingerprint !== null
            && $currentFingerprint !== $this->source_fingerprint;
    }
}
