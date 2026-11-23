<?php

namespace App\Enums;

/**
 * How a student's result details reach their parent/guardian.
 */
enum ResultDeliveryChannel: string
{
    case Email = 'email';
    case WhatsApp = 'whatsapp';

    public function label(): string
    {
        return match ($this) {
            self::Email => 'Email',
            self::WhatsApp => 'WhatsApp',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::Email => 'fa-solid fa-envelope',
            self::WhatsApp => 'fa-brands fa-whatsapp',
        };
    }

    /**
     * The channels a "send via" choice means.
     *
     * @return list<self>
     */
    public static function fromChoice(string $choice): array
    {
        return match ($choice) {
            'email' => [self::Email],
            'whatsapp' => [self::WhatsApp],
            'both' => [self::Email, self::WhatsApp],
            default => [],
        };
    }
}
