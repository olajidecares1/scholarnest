<?php

namespace App\Services\WhatsApp;

use Illuminate\Support\Facades\Http;
use Illuminate\Validation\ValidationException;
use Throwable;

/**
 * Asks Meta about a WhatsApp Business phone number with a token: proves both
 * are right before they are saved, and gives the number as Meta shows it.
 */
final class MetaNumberCheck
{
    /**
     * @return array{display_phone_number?: string, verified_name?: string}
     *
     * @throws ValidationException with Meta's own reason
     */
    public static function check(string $phoneNumberId, string $token): array
    {
        try {
            $response = Http::withToken($token)
                ->acceptJson()
                ->connectTimeout(5)
                ->timeout(10)
                ->get(sprintf('https://graph.facebook.com/%s/%s', config('services.whatsapp.api_version', 'v21.0'), $phoneNumberId), [
                    'fields' => 'display_phone_number,verified_name',
                ]);
        } catch (Throwable $e) {
            report($e);

            throw ValidationException::withMessages(['phone_number_id' => 'Meta could not be reached to check these details. Please try again in a moment.']);
        }

        if (! $response->successful()) {
            throw ValidationException::withMessages(['access_token' => 'Meta did not accept these details: '
                .($response->json('error.message') ?? "HTTP {$response->status()}").' Check the Phone number ID and the access token.']);
        }

        return (array) $response->json();
    }
}
