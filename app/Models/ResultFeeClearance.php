<?php

namespace App\Models;

use App\Enums\ExamTerm;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One school's decision to release one student's results for one term
 * despite an outstanding fee balance.
 *
 * The absence of a row is the normal case, not a missing one: results are
 * withheld while money is owed, and this record is the school saying
 * otherwise for a particular student and term.
 */
class ResultFeeClearance extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'student_id',
        'session',
        'term',
        'released_by',
        'released_at',
        'reason',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'term' => ExamTerm::class,
            'released_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function releasedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'released_by');
    }
}
