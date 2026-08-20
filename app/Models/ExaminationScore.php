<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ExaminationScoreFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationScore extends Model
{
    /** @use HasFactory<ExaminationScoreFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'examination_subject_id',
        'student_id',
        'score',
        'test_score',
        'exam_score',
        'remark',
        'grade_override',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'score' => 'decimal:2',
            'test_score' => 'decimal:2',
            'exam_score' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<ExaminationSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(ExaminationSubject::class, 'examination_subject_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function percentage(): float
    {
        return $this->subject->max_score > 0
            ? round(((float) $this->score / $this->subject->max_score) * 100, 1)
            : 0.0;
    }

    public function grade(): string
    {
        if ($this->grade_override) {
            return $this->grade_override;
        }

        return GradeBand::resolve($this->subject->examination->school, $this->percentage());
    }
}
