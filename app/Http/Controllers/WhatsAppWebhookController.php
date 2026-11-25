<?php

namespace App\Http\Controllers;

use App\Services\ResultDetails\ResultDetailsSender;
use App\Services\WhatsApp\SchoolWhatsAppVerifier;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Http\Response;

/**
 * WhatsApp's delivery receipts, from Meta's WhatsApp Business Cloud API.
 *
 * WhatsApp accepting a message is not the same as the parent receiving it: a
 * message outside the 24-hour window, to a number not on WhatsApp, or with a
 * template problem, is accepted first and failed afterwards. These receipts
 * are how the sending history learns that, so a message that never arrived
 * is shown as Failed, with WhatsApp's reason, instead of staying "Sent".
 *
 *   GET   Meta's one-off check that this address is ours (the verify token).
 *   POST  receipts. Each must carry Meta's signature over the exact body,
 *         made with the app secret; anything unsigned or mis-signed is
 *         refused, so nobody can mark messages delivered or failed.
 */
class WhatsAppWebhookController extends Controller
{
    public function verify(Request $request): Response
    {
        $expected = (string) config('services.whatsapp.webhook_verify_token');

        abort_unless(
            $expected !== ''
                && $request->query('hub_mode') === 'subscribe'
                && hash_equals($expected, (string) $request->query('hub_verify_token')),
            403,
        );

        return response((string) $request->query('hub_challenge'), 200, ['Content-Type' => 'text/plain']);
    }

    public function receive(Request $request, ResultDetailsSender $sender, SchoolWhatsAppVerifier $verifier): JsonResponse
    {
        $secret = (string) config('services.whatsapp.app_secret');
        $signature = (string) $request->header('X-Hub-Signature-256');

        abort_if($secret === '' || ! hash_equals('sha256='.hash_hmac('sha256', $request->getContent(), $secret), $signature), 403);

        foreach ((array) $request->input('entry', []) as $entry) {
            foreach ((array) ($entry['changes'] ?? []) as $change) {
                foreach ((array) ($change['value']['statuses'] ?? []) as $status) {
                    if (! is_array($status) || blank($status['id'] ?? null) || blank($status['status'] ?? null)) {
                        continue;
                    }

                    $error = $status['errors'][0] ?? null;
                    $reason = is_array($error)
                        ? trim(($error['title'] ?? '').(filled($error['error_data']['details'] ?? null) ? ': '.$error['error_data']['details'] : ''))
                        : null;

                    $sender->receipt((string) $status['id'], (string) $status['status'], $reason ?: null);

                    // Or a code sent to verify a school's WhatsApp number.
                    $verifier->receipt((string) $status['id'], (string) $status['status'], $reason ?: null);
                }
            }
        }

        // Always 200 for a signed request, or Meta keeps resending it.
        return response()->json(['received' => true]);
    }
}
