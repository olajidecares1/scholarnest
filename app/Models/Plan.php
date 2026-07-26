<?php

namespace App\Models;

use App\Enums\PlanKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Plan extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'name',
        'tagline',
        'currency',
        'price_per_student_per_term',
        'price_monthly',
        'price_per_term',
        'has_custom_pricing',
        'features',
        'is_popular',
        'sort_order',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'key' => PlanKey::class,
            'price_per_student_per_term' => 'decimal:2',
            'price_monthly' => 'decimal:2',
            'price_per_term' => 'decimal:2',
            'has_custom_pricing' => 'boolean',
            'features' => 'array',
            'is_popular' => 'boolean',
        ];
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }
}
