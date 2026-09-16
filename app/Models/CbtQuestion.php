<?php

namespace App\Models;

use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\CbtQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

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
        'cbt_document_upload_id',
        'question_text',
        'marks',
        'image_path',
        'sort_order',
        'needs_review',
        'review_notes',
        'question_number',
        'passage',
        'explanation',
        'fingerprint',
        'is_published',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'needs_review' => 'boolean',
            'is_published' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<CbtExam, $this>
     */
    public function exam(): BelongsTo
    {
        return $this->belongsTo(CbtExam::class, 'cbt_exam_id');
    }

    /**
     * @return BelongsTo<CbtDocumentUpload, $this>
     */
    public function documentUpload(): BelongsTo
    {
        return $this->belongsTo(CbtDocumentUpload::class, 'cbt_document_upload_id');
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
        return UploadStorage::publicUrl($this->image_path);
    }
}
