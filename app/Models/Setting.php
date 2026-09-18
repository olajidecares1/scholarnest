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
        return self::query()->firstOrCreate([], [
            'site_name' => config('app.name', 'AkademicNest'),
            'support_email' => config('mail.from.address'),
            'notification_from_name' => config('app.name', 'AkademicNest'),
            'notification_from_email' => config('mail.from.address'),
        ]);
    }

    /**
     * Where to tell somebody to write for help.
     *
     * Read-only and creates nothing, so it is safe on a public page. Falls back
     * to the address mail is sent from, which is the one that certainly exists:
     * a "Contact Support" button that goes nowhere is worse than a plain
     * sentence, and this page shipped with href="#".
     */
    public static function supportEmail(): ?string
    {
        return self::query()->value('support_email') ?: config('mail.from.address');
    }

    /**
     * The uploaded platform logo's stored path, or null.
     *
     * READ-ONLY, and creates nothing, unlike current(). The PWA icon route is
     * public and is fetched by a browser before anybody signs in, so it must
     * never be the thing that writes a settings row.
     */
    public static function platformLogoPath(): ?string
    {
        return self::query()->value('logo_path') ?: null;
    }

    /**
     * The platform logo's address, or a bundled mark when none is uploaded.
     *
     * SERVED BY THE APPLICATION, from the database, see BrandingImage. Every
     * template used to point straight at the disk, first through Storage::url()
     * on the wrong disk and then through the right one, and neither survived
     * production: that disk is a directory that is not served at /storage and
     * is wiped by every deploy, so the logo was broken on every page however it
     * was addressed.
     */
    public function logoUrl(string $fallback = 'images/logo-icon-dark.png'): string
    {
        return $this->logo_path
            ? BrandingImage::url($this->logo_path)
            : asset($fallback);
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
