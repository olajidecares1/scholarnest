<?php

namespace App\Models;

use App\Enums\CbtDocumentUploadStatus;
use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\CbtTestDocumentUploadFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\Storage;

class CbtTestDocumentUpload extends Model
{
    /** @use HasFactory<CbtTestDocumentUploadFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'cbt_test_id',
        'staff_id',
        'original_filename',
        'disk',
        'path',
        'mime_type',
        'status',
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
            'ai_response' => 'array',
            'extracted_images' => 'array',
            'processed_at' => 'datetime',
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
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<CbtTestQuestion, $this>
     */
    public function questions(): HasMany
    {
        return $this->hasMany(CbtTestQuestion::class);
    }

    public function absolutePath(): string
    {
        // A real local file even when the disk is object storage - the
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
