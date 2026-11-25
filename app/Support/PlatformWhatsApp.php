<?php

namespace App\Support;

use App\Models\PlatformWhatsAppSetting;
use Illuminate\Support\Facades\Schema;
use Throwable;

/**
 * AkademicNest's own WhatsApp settings, wherever they were set.
 *
 * What the Super Admin saved (Super Admin > WhatsApp) wins; otherwise the
 * WHATSAPP_* server settings (config/services.php). Kept for a few seconds
 * at most, so a batch of messages does not read the row for every one, while
 * a change in Super Admin still counts almost at once, without a deploy, even
 * on a server that keeps running between requests.
 */
final class PlatformWhatsApp
{
    private const KEEP_SECONDS = 5;

    /** @var array{at: float, app: int, values: array<string, mixed>}|null */
    private static ?array $memo = null;

    /** Keys that can be saved in Super Admin, as named in services.whatsapp. */
    private const SAVED = [
        'token' => 'access_token',
        'phone_number_id' => 'phone_number_id',
        'result_template' => 'result_template',
        'verify_template' => 'verify_template',
        'template_language' => 'template_language',
        'verify_template_language' => 'template_language',
        'app_secret' => 'app_secret',
        'webhook_verify_token' => 'webhook_verify_token',
    ];

    public static function get(string $key, mixed $default = null): mixed
    {
        $column = self::SAVED[$key] ?? null;
        $saved = $column !== null ? (self::saved()[$column] ?? null) : null;

        return filled($saved) ? $saved : config("services.whatsapp.{$key}", $default);
    }

    /** Whether the Super Admin has saved a number (rather than server settings). */
    public static function savedInSuperAdmin(): bool
    {
        $saved = self::saved();

        return filled($saved['phone_number_id'] ?? null) && filled($saved['access_token'] ?? null);
    }

    /** @return array<string, mixed> */
    private static function saved(): array
    {
        // Also tied to this application instance, so a fresh one (each test)
        // never sees another's values.
        if (self::$memo !== null && self::$memo['app'] === spl_object_id(app()) && microtime(true) - self::$memo['at'] < self::KEEP_SECONDS) {
            return self::$memo['values'];
        }

        $values = (function (): array {
            try {
                if (! Schema::hasTable('platform_whatsapp_settings')) {
                    return [];
                }

                $row = PlatformWhatsAppSetting::query()->first();

                return $row ? array_merge($row->only(array_values(self::SAVED)), [
                    'access_token' => $row->access_token,
                    'app_secret' => $row->app_secret,
                ]) : [];
            } catch (Throwable) {
                return [];
            }
        })();

        self::$memo = ['at' => microtime(true), 'app' => spl_object_id(app()), 'values' => $values];

        return $values;
    }

    /** After a save: the next read sees it. */
    public static function forget(): void
    {
        self::$memo = null;
    }
}
