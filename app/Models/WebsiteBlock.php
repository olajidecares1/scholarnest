<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\WebsiteBlockFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class WebsiteBlock extends Model
{
    /** @use HasFactory<WebsiteBlockFactory> */
    use HasFactory, HasUuidRouteKey;

    protected $fillable = [
        'school_id',
        'page',
        'section',
        'type',
        'content',
        'secondary_content',
        'url',
        'x',
        'y',
        'w',
        'h',
        'style',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'style' => 'array',
            'x' => 'float',
            'y' => 'float',
            'w' => 'float',
            'h' => 'float',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @param  Builder<WebsiteBlock>  $query
     * @return Builder<WebsiteBlock>
     */
    public function scopeForPage(Builder $query, string $page): Builder
    {
        return $query->where('page', $page);
    }
}
