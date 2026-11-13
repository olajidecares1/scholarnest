<?php

namespace App\Services\WhatsApp;

/**
 * What happened to one WhatsApp message.
 */
final class WhatsAppDelivery
{
    private function __construct(
        public readonly bool $sent,
        public readonly ?string $error = null,
        public readonly ?string $messageId = null,
    ) {}

    public static function sent(?string $messageId = null): self
    {
        return new self(true, null, $messageId);
    }

    public static function failed(string $error): self
    {
        return new self(false, $error);
    }
}
