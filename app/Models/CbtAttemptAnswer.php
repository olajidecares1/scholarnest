<?php

namespace App\Models;

use Database\Factories\CbtAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtAttemptAnswer extends Model
{
    /** @use HasFactory<CbtAttemptAnswerFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_attempt_id',
        'cbt_question_id',
        'cbt_question_option_id',
        'is_correct',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_correct' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CbtAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CbtAttempt::class, 'cbt_attempt_id');
    }

    /**
     * @return BelongsTo<CbtQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CbtQuestion::class, 'cbt_question_id');
    }

    /**
     * @return BelongsTo<CbtQuestionOption, $this>
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(CbtQuestionOption::class, 'cbt_question_option_id');
    }
}
