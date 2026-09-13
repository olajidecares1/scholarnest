<?php

namespace App\Models;

use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\NewsPostFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Str;

class NewsPost extends Model
{
    /** @use HasFactory<NewsPostFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'title',
        'excerpt',
        'body',
        'image_path',
        'category',
        'is_published',
        'published_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_published' => 'boolean',
            'published_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function imageUrl(): ?string
    {
        return UploadStorage::publicUrl($this->image_path);
    }

    public function summary(): string
    {
        return $this->excerpt ?: Str::limit(strip_tags($this->body), 140);
    }
}
