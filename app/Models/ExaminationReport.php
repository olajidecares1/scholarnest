<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ExaminationReportFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ExaminationReport extends Model
{
    /** @use HasFactory<ExaminationReportFactory> */
    use HasFactory, HasUuidRouteKey;

    protected $fillable = [
        'school_id', 'examination_id', 'student_id',
        'teacher_remark', 'principal_remark', 'last_sent_at', 'last_sent_by',

        // Which administrator wrote the Principal's remark, and when. The
        // session, term and class are NOT copied here, they belong to the
        // examination this report hangs off, so there is one answer to
        // "which term is this?" rather than two that can disagree.
        'principal_remark_by', 'principal_remark_at',
    ];

    protected function casts(): array
    {
        return [
            'last_sent_at' => 'datetime',
            'principal_remark_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    public function lastSentBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'last_sent_by');
    }

    public static function firstOrCreateFor(Examination $examination, Student $student): self
    {
        return self::firstOrCreate(
            ['examination_id' => $examination->id, 'student_id' => $student->id],
            ['school_id' => $examination->school_id],
        );
    }
}
