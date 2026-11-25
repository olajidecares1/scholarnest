<?php

namespace App\Services\WhatsApp;

use App\Models\School;
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
    /**
     * @param  array{token: string, phone_number_id: string, result_template: ?string, template_language: string}|null  $account
     *                                                                                                                            a school's own number; null for the platform's (config)
     */
    public function __construct(private readonly ?array $account = null) {}

    public function forSchool(?School $school): WhatsAppGateway
    {
        // Read fresh: connected or disconnected a moment ago counts now.
        $account = $school?->whatsAppAccount()->first();

        if ($account === null || blank($account->access_token) || blank($account->phone_number_id)) {
            return new self;
        }

        return new self([
            'token' => (string) $account->access_token,
            'phone_number_id' => (string) $account->phone_number_id,
            'result_template' => $account->result_template,
            'template_language' => $account->template_language ?: 'en',
        ]);
    }

    public function isConfigured(): bool
    {
        return filled($this->token()) && filled($this->phoneNumberId());
    }

    public function resultTemplate(): ?string
    {
        return $this->account !== null
            ? ($this->account['result_template'] ?: null)
            : (config('services.whatsapp.result_template') ?: null);
    }

    public function fromSchoolNumber(): bool
    {
        return $this->account !== null;
    }

    public function canSendCodes(): bool
    {
        return $this->account === null
            && $this->isConfigured()
            && filled(config('services.whatsapp.verify_template'));
    }

    public function sendCode(string $phone, string $code): WhatsAppDelivery
    {
        if (! $this->canSendCodes()) {
            return WhatsAppDelivery::failed('WhatsApp verification is not switched on yet.');
        }

        $phone = (string) preg_replace('/\D/', '', $phone);

        // An Authentication template: the code fills the body, and its
        // "Copy code" button carries it too.
        return $this->post([
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => (string) config('services.whatsapp.verify_template'),
                'language' => ['code' => (string) config('services.whatsapp.verify_template_language', 'en')],
                'components' => [
                    ['type' => 'body', 'parameters' => [['type' => 'text', 'text' => $code]]],
                    ['type' => 'button', 'sub_type' => 'url', 'index' => '0', 'parameters' => [['type' => 'text', 'text' => $code]]],
                ],
            ],
        ]);
    }

    private function token(): ?string
    {
        return $this->account['token'] ?? config('services.whatsapp.token');
    }

    private function phoneNumberId(): ?string
    {
        return $this->account['phone_number_id'] ?? config('services.whatsapp.phone_number_id');
    }

    private function templateLanguage(): string
    {
        return $this->account['template_language'] ?? config('services.whatsapp.template_language', 'en');
    }

    public function send(string $phone, string $message, array $templateValues = [], ?string $template = self::DEFAULT_TEMPLATE): WhatsAppDelivery
    {
        if (! $this->isConfigured()) {
            return WhatsAppDelivery::failed('WhatsApp sending is not set up yet.');
        }

        $phone = (string) preg_replace('/\D/', '', $phone);

        if ($phone === '') {
            return WhatsAppDelivery::failed('No phone number.');
        }

        if ($template === self::DEFAULT_TEMPLATE) {
            $template = config('services.whatsapp.template');
        }

        $payload = filled($template) ? [
            'messaging_product' => 'whatsapp',
            'to' => $phone,
            'type' => 'template',
            'template' => [
                'name' => $template,
                'language' => ['code' => $this->templateLanguage()],
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

        return $this->post($payload);
    }

    /**
     * @param  array<string, mixed>  $payload
     */
    private function post(array $payload): WhatsAppDelivery
    {
        $url = sprintf(
            'https://graph.facebook.com/%s/%s/messages',
            config('services.whatsapp.api_version', 'v21.0'),
            $this->phoneNumberId(),
        );

        try {
            $response = Http::withToken((string) $this->token())
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
