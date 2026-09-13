<?php

namespace App\Models;

use App\Services\Uploads\UploadStorage;
use App\Support\HasUuidRouteKey;
use Database\Factories\HeroSlideFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class HeroSlide extends Model
{
    /** @use HasFactory<HeroSlideFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'image_path',
        'sort_order',
    ];

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
}
