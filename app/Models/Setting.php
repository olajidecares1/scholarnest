<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\Storage;

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
     * The platform logo's address, or a bundled mark when none is uploaded.
     *
     * FROM THE PUBLIC DISK, because that is the disk ThemeController writes
     * the logo to. Nine templates used to build this address with
     * Storage::url(), which asks the DEFAULT disk - the private one. On a
     * single server the two happen to produce the same "/storage/..." string,
     * so it looked right. Once the public disk is object storage they part
     * company: the file is in the bucket and every page points at a /storage
     * path that does not exist, so the logo renders broken everywhere while the
     * favicon, which already asked the right disk, works.
     */
    public function logoUrl(string $fallback = 'images/logo-icon-dark.png'): string
    {
        return $this->logo_path
            ? Storage::disk('public')->url($this->logo_path)
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
