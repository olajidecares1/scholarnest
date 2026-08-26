<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * A result-checking address a school has stopped using.
 *
 * Kept so it can never be issued again. A result link is a thing schools print
 * on slips and forward around WhatsApp groups, so copies of a retired one stay
 * in circulation long after the school has moved on. If the address were
 * released back into the pool and later handed to a different school, every
 * parent still holding it would be walked to the wrong school's token prompt -
 * the exact cross-school confusion the result-link design exists to prevent.
 *
 * Rows are never deleted, and they outlive the school itself: `school_id` goes
 * null if the school is removed, but the address stays claimed.
 */
class RetiredSchoolResultLink extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'slug',
        'retired_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'retired_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }
}
