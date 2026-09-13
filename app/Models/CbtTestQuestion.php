<?php

namespace App\Models;

use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\CbtTestQuestionFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class CbtTestQuestion extends Model
{
    /** @use HasFactory<CbtTestQuestionFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_test_id',
        'cbt_test_document_upload_id',
        'question_text',
        'marks',
        'image_path',
        'sort_order',
        'needs_review',
        'review_notes',
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
        ];
    }

    /**
     * @return BelongsTo<CbtTest, $this>
     */
    public function test(): BelongsTo
    {
        return $this->belongsTo(CbtTest::class, 'cbt_test_id');
    }

    /**
     * @return BelongsTo<CbtTestDocumentUpload, $this>
     */
    public function documentUpload(): BelongsTo
    {
        return $this->belongsTo(CbtTestDocumentUpload::class, 'cbt_test_document_upload_id');
    }

    /**
     * @return HasMany<CbtTestQuestionOption, $this>
     */
    public function options(): HasMany
    {
        return $this->hasMany(CbtTestQuestionOption::class)->orderBy('label');
    }

    public function correctOption(): ?CbtTestQuestionOption
    {
        return $this->options->firstWhere('is_correct', true);
    }

    public function imageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->image_path);
    }
}
