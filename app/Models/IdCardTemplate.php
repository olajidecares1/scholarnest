<?php

namespace App\Models;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Support\HasUuidRouteKey;
use Database\Factories\IdCardTemplateFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

class IdCardTemplate extends Model
{
    /** @use HasFactory<IdCardTemplateFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'name',
        'type',
        'orientation',
        'primary_color',
        'secondary_color',
        'instructions',
        'background_path',
        'show_blood_group',
        'show_dob',
        'is_default',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'type' => IdCardHolderType::class,
            'orientation' => IdCardOrientation::class,
            'show_blood_group' => 'boolean',
            'show_dob' => 'boolean',
            'is_default' => 'boolean',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function backgroundUrl(): ?string
    {
        return $this->background_path ? Storage::disk('public')->url($this->background_path) : null;
    }
}
