<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Throwable;

/**
 * WhatsApp through Meta's WhatsApp Business Cloud API.
 *
 * Uses the approved template named in config('services.whatsapp.template')
 * when there is one, because WhatsApp only delivers a business-initiated
 * message as a template. Without one it sends plain text, which arrives only
 * for someone who has messaged the school's number within the last 24 hours.
 */
class CloudApiWhatsAppGateway implements WhatsAppGateway
{
    public function isConfigured(): bool
    {
        return filled(config('services.whatsapp.token')) && filled(config('services.whatsapp.phone_number_id'));
    }

    public function send(string $phone, string $message, array $templateValues = []): WhatsAppDelivery
    {
        if (! $this->isConfigured()) {
            return WhatsAppDelivery::failed('WhatsApp sending is not set up yet.');
        }

        $phone = (string) preg_replace('/\D/', '', $phone);

        if ($phone === '') {
            return WhatsAppDelivery::failed('No phone number.');
        }

        $template = config('services.whatsapp.template');

        $payload = filled($template) ? [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => config('services.whatsapp.template_language', 'en')],
                'components' => [[
                    'type' => 'body',
                    'parameters' => array_map(fn ($value) => ['type' => 'text', 'text' => (string) $value], array_values($templateValues)),
                ]],
            ],
        ] : [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'text',
            'text' => ['preview_url' => true, 'body' => $message],
        ];

        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version', 'v21.0'),
            config('services.whatsapp.phone_number_id'),
        );

        try {
            $response = Http::withToken((string) config('services.whatsapp.token'))
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout((int) config('services.whatsapp.timeout', 10))
                ->post($url, $payload);
        } catch (Throwable $e) {
            report($e);

            return WhatsAppDelivery::failed('WhatsApp could not be reached. Please try again later.');
        }

        if ($response->successful()) {
            return WhatsAppDelivery::sent($response->json('messages.0.id'));
        }

        // Meta's own explanation ("Recipient phone number not in allowed
        // list", "Template name does not exist") is the useful part.
        $reason = $response->json('error.error_data.details')
            ?? $response->json('error.message')
            ?? "WhatsApp refused the message (HTTP {$response->status()}).";

        return WhatsAppDelivery::failed((string) $reason);
    }
}
