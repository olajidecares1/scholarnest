<?php

namespace App\Models;

use App\Enums\JobQuestionType;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An extra question a school asks everyone applying for one vacancy.
 */
class JobPostingQuestion extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'job_posting_id',
        'question',
        'type',
        'options',
        'is_required',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => JobQuestionType::class,
            'options' => 'array',
            'is_required' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<JobPosting, $this>
     */
    public function jobPosting(): BelongsTo
    {
        return $this->belongsTo(JobPosting::class);
    }

    /** The form field name its answer is posted under. */
    public function fieldName(): string
    {
        return "answers.{$this->id}";
    }

    /**
     * @return list<string>
     */
    public function choices(): array
    {
        return match ($this->type) {
            JobQuestionType::YesNo => ['Yes', 'No'],
            JobQuestionType::Choice => array_values(array_filter((array) $this->options, fn ($option) => filled($option))),
            default => [],
        };
    }
}
