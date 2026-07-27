<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\CbtQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CbtQuestion extends Model
{
    /** @use HasFactory<CbtQuestionFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_exam_id',
        'question_text',
        'image_path',
        'sort_order',
    ];

    /**
     * @return BelongsTo<CbtExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(CbtExam::class, 'cbt_exam_id');
    }

    /**
     * @return HasMany<CbtQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(CbtQuestionOption::class)->orderBy('label');
    }

    public function correctOption(): ?CbtQuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function imageUrl(): ?string
    {
        return $this->image_path ? Storage::disk('public')->url($this->image_path) : null;
    }
}
