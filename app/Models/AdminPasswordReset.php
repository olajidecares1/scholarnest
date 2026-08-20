<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class AdminPasswordReset extends Model
{
    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'user_id',
        'link_token_hash',
        'code_hash',
        'attempts',
        'expires_at',
        'code_verified_at',
        'used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'expires_at' => 'datetime',
            'code_verified_at' => 'datetime',
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Unused and unexpired - not yet verified or completed.
     *
     * @param  Builder<AdminPasswordReset>  $query
     * @return Builder<AdminPasswordReset>
     */
    public function scopeActive(Builder $query): Builder
    {
        return $query->whereNull('used_at')->where('expires_at', '>', now());
    }
}
