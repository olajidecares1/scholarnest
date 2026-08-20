<?php

namespace App\Models;

use Database\Factories\CbtTestAttemptAnswerFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtTestAttemptAnswer extends Model
{
    /** @use HasFactory<CbtTestAttemptAnswerFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_test_attempt_id',
        'cbt_test_question_id',
        'cbt_test_question_option_id',
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
     * @return BelongsTo<CbtTestAttempt, $this>
     */
    public function attempt(): BelongsTo
    {
        return $this->belongsTo(CbtTestAttempt::class, 'cbt_test_attempt_id');
    }

    /**
     * @return BelongsTo<CbtTestQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CbtTestQuestion::class, 'cbt_test_question_id');
    }

    /**
     * @return BelongsTo<CbtTestQuestionOption, $this>
     */
    public function selectedOption(): BelongsTo
    {
        return $this->belongsTo(CbtTestQuestionOption::class, 'cbt_test_question_option_id');
    }
}
