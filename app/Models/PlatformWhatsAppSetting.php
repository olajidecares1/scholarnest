<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * AkademicNest's WhatsApp Business number, set by the Super Admin. One row.
 * Read through App\Support\PlatformWhatsApp, never directly.
 */
class PlatformWhatsAppSetting extends Model
{
    protected $table = 'platform_whatsapp_settings';

    protected $fillable = [
        'phone_number_id', 'access_token', 'result_template', 'verify_template', 'template_language',
        'app_secret', 'webhook_verify_token', 'display_number', 'verified_name', 'checked_at',
    ];

    protected $hidden = ['access_token', 'app_secret'];

    protected function casts(): array
    {
        return [
            'access_token' => 'encrypted',
            'app_secret' => 'encrypted',
            'checked_at' => 'datetime',
        ];
    }

    public static function current(): self
    {
        return self::query()->firstOrCreate([]);
    }

    /** "••••••••1234": enough to recognise, never enough to use. */
    public static function mask(?string $secret): ?string
    {
        return filled($secret) ? '••••••••'.substr((string) $secret, -4) : null;
    }
}
