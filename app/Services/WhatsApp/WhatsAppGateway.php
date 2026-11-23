<?php

namespace App\Services\WhatsApp;

/**
 * Sends a WhatsApp message on the school's behalf, without anyone pressing
 * Send. Bound in AppServiceProvider.
 */
interface WhatsAppGateway
{
    /** Use the default template, config('services.whatsapp.template'). */
    public const DEFAULT_TEMPLATE = '__default__';

    /** Whether messages can be sent at all (credentials are set). */
    public function isConfigured(): bool;

    /**
     * @param  string  $phone  digits only, in international form (2348031234567)
     * @param  string  $message  the full text, used when no template is configured
     * @param  list<string>  $templateValues  the template's {{1}}..{{n}}, in order
     * @param  string|null  $template  the approved template to use: the
     *                                 default one, another by name, or null
     *                                 for plain text
     */
    public function send(string $phone, string $message, array $templateValues = [], ?string $template = self::DEFAULT_TEMPLATE): WhatsAppDelivery;
}
