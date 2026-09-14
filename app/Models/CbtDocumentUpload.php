<?php

namespace App\Models;

use App\Enums\CbtDocumentUploadStatus;
use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\CbtDocumentUploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CbtDocumentUpload extends Model
{
    /** @use HasFactory<CbtDocumentUploadFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'uploaded_by',
        'cbt_exam_body_id',
        'cbt_subject_id',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'status',
        'detected_years',
        'questions_extracted_count',
        'questions_needing_review_count',
        'error_message',
        'ai_response',
        'extracted_images',
        'processed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'status' => CbtDocumentUploadStatus::class,
            'detected_years' => 'array',
            'ai_response' => 'array',
            'extracted_images' => 'array',
            'processed_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function uploadedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'uploaded_by');
    }

    /**
     * @return BelongsTo<CbtExamBody, $this>
     */
    public function examBody(): BelongsTo
    {
        return $this->belongsTo(CbtExamBody::class, 'cbt_exam_body_id');
    }

    /**
     * @return BelongsTo<CbtSubject, $this>
     */
    public function subject(): BelongsTo
    {
        return $this->belongsTo(CbtSubject::class, 'cbt_subject_id');
    }

    /**
     * @return HasMany<CbtQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CbtQuestion::class);
    }

    public function absolutePath(): string
    {
        // A real local file even when the disk is object storage, the
        // extractors need one. See UploadStorage::localPath().
        return app(UploadStorage::class)->localPath($this->disk, $this->path)
            ?? Storage::disk($this->disk)->path($this->path);
    }

    /**
     * @return list<string>
     */
    public function extractedImageUrls(): array
    {
        return collect($this->extracted_images ?? [])
            ->map(fn (string $path) => UploadStorage::publicUrl($path))
            ->all();
    }
}
