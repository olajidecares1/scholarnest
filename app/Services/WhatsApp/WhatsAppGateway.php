<?php

namespace App\Services\WhatsApp;

use App\Models\School;

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
     * The gateway that sends for this school: from the school's own
     * registered WhatsApp Business number when it has connected one, else
     * the platform's (which may not be set up either).
     */
    public function forSchool(?School $school): self;

    /**
     * The approved template for result details on this gateway's number,
     * or null when there is none (plain text, delivered only within
     * WhatsApp's 24-hour reply window).
     */
    public function resultTemplate(): ?string;

    /** Whether this gateway sends from a school's own number (not the platform's). */
    public function fromSchoolNumber(): bool;

    /**
     * Whether a verification code can be sent: the platform's number is set
     * up and has an approved Authentication template
     * (config('services.whatsapp.verify_template')).
     */
    public function canSendCodes(): bool;

    /**
     * Send a one-time code, through the Authentication template, from the
     * platform's number. Proves a number is on WhatsApp and belongs to
     * whoever types the code back.
     *
     * @param  string  $phone  digits only, in international form
     */
    public function sendCode(string $phone, string $code): WhatsAppDelivery;

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
