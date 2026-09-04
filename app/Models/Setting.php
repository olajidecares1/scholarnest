<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class Setting extends Model
{
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'site_name',
        'support_email',
        'support_phone',
        'notification_from_name',
        'notification_from_email',
        'maintenance_mode',
        'maintenance_message',
        'theme_preset',
        'logo_path',
        'favicon_path',
        'login_background_media_id',
        'register_background_media_id',
        'result_token_max_uses',
        'result_token_expiry_days',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'maintenance_mode' => 'boolean',
            'result_token_max_uses' => 'integer',
            'result_token_expiry_days' => 'integer',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([], ['site_name' => config('app.name', 'ScholarNest')]);
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function loginBackgroundMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'login_background_media_id');
    }

    /**
     * @return BelongsTo<Media, $this>
     */
    public function registerBackgroundMedia(): BelongsTo
    {
        return $this->belongsTo(Media::class, 'register_background_media_id');
    }
}
