<?php

namespace App\Models;

use Database\Factories\CbtTestQuestionOptionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CbtTestQuestionOption extends Model
{
    /** @use HasFactory<CbtTestQuestionOptionFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_test_question_id',
        'label',
        'option_text',
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
     * @return BelongsTo<CbtTestQuestion, $this>
     */
    public function question(): BelongsTo
    {
        return $this->belongsTo(CbtTestQuestion::class, 'cbt_test_question_id');
    }
}
