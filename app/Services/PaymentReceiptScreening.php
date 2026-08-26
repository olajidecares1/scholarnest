<?php

namespace App\Services;

use Illuminate\Http\Client\Response;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Http;
use Illuminate\Support\Facades\Log;
use Throwable;

/**
 * A doorman for payment receipts, not an auditor.
 *
 * This exists to stop the obvious: a selfie, a blank page, a screenshot of
 * something that is not a payment, a receipt for far less than the plan costs.
 * It turns those away at the door with a reason the school can act on.
 *
 * What it deliberately does NOT do is approve anything. A document that passes
 * every check here is still only "worth a person looking at" - it is stored,
 * the school is told it is under review, and the EduNest Team activates the
 * subscription by hand as before. Nothing in this class shortens that.
 *
 * The reason for that line is simple: a carefully edited receipt will pass
 * every check that can be written. If the system announced such a document as
 * "verified", whoever reviews payments would reasonably stop looking - and that
 * is exactly the moment a forgery gets through. Screening that only ever
 * rejects makes the review easier without ever making it feel unnecessary.
 */
class PaymentReceiptScreening
{
    private const API_URL = 'https://api.anthropic.com/v1/messages';

    private const ANTHROPIC_VERSION = '2023-06-01';

    private const TOOL_NAME = 'record_receipt_assessment';

    /**
     * How far below the required amount is still let through to a human.
     *
     * Not zero, because the figure read off a photographed receipt is not
     * exact - a smudged separator turns 500,000 into 500.00 - and a school that
     * genuinely paid should never be turned away by a misread digit. Anything
     * inside this margin goes to review with the discrepancy noted; only a
     * clear, large shortfall is refused outright.
     */
    private const AMOUNT_TOLERANCE = 0.02;

    /**
     * Look at a receipt before it is accepted.
     *
     * @param  float  $requiredAmount  What the plan or top-up actually costs.
     * @return array{passed: bool, reason: ?string, notes: ?string}
     */
    public function screen(UploadedFile $receipt, float $requiredAmount, string $schoolName): array
    {
        try {
            $assessment = $this->assess($receipt, $schoolName);
        } catch (Throwable $e) {
            // The screening service is unavailable - no credit, no key, an
            // outage. A school that has paid must not be blocked because our
            // billing lapsed, so the upload goes through and the person
            // reviewing it is told the automatic check never ran.
            Log::warning('Payment receipt screening unavailable: '.$e->getMessage());

            return [
                'passed' => true,
                'reason' => null,
                'notes' => 'Automatic screening did not run ('.$e->getMessage().'). Review this receipt manually.',
            ];
        }

        if (! ($assessment['is_payment_receipt'] ?? false)) {
            return [
                'passed' => false,
                'reason' => $this->notAReceiptMessage($assessment),
                'notes' => null,
            ];
        }

        $shortfall = $this->shortfall($assessment, $requiredAmount);

        if ($shortfall !== null) {
            return [
                'passed' => false,
                'reason' => sprintf(
                    'Insufficient Payment. The receipt you uploaded shows %s, but the amount required is %s. '
                        .'Please make the full payment and upload a receipt showing it.',
                    '₦'.number_format($shortfall['found'], 2),
                    '₦'.number_format($shortfall['required'], 2),
                ),
                'notes' => null,
            ];
        }

        // Everything that could be read looks like a payment. That is as far as
        // this goes - the note below is what the reviewer sees, and it does not
        // say "verified".
        return [
            'passed' => true,
            'reason' => null,
            'notes' => $this->reviewerNotes($assessment),
        ];
    }

    /**
     * Why this does not look like a payment receipt, in the school's terms.
     *
     * @param  array<string, mixed>  $assessment
     */
    private function notAReceiptMessage(array $assessment): string
    {
        $observed = trim((string) ($assessment['what_it_looks_like'] ?? ''));

        return 'Payment Verification Failed. '
            .($observed !== ''
                ? "The file you uploaded looks like {$observed} rather than a proof of payment. "
                : 'We could not read a payment from the file you uploaded. ')
            .'Please upload a clear photo, screenshot or PDF of your bank transfer receipt or teller, '
            .'showing the amount paid, the date and the transaction reference.';
    }

    /**
     * A shortfall worth refusing over, or null.
     *
     * @param  array<string, mixed>  $assessment
     * @return array{found: float, required: float}|null
     */
    private function shortfall(array $assessment, float $requiredAmount): ?array
    {
        $found = $assessment['amount'] ?? null;

        // No readable amount is not a refusal. Plenty of legitimate receipts
        // photograph badly, and a person can read what a model could not.
        if (! is_numeric($found) || $requiredAmount <= 0) {
            return null;
        }

        $found = (float) $found;

        if ($found >= $requiredAmount * (1 - self::AMOUNT_TOLERANCE)) {
            return null;
        }

        return ['found' => $found, 'required' => $requiredAmount];
    }

    /**
     * What the reviewer is told. Facts read off the document, never a verdict.
     *
     * @param  array<string, mixed>  $assessment
     */
    private function reviewerNotes(array $assessment): string
    {
        $parts = [];

        foreach ([
            'amount' => 'Amount',
            'paid_on' => 'Date',
            'reference' => 'Reference',
            'payer' => 'Payer',
            'payee' => 'Paid to',
            'bank' => 'Bank',
        ] as $key => $label) {
            $value = trim((string) ($assessment[$key] ?? ''));

            if ($value !== '') {
                $parts[] = "{$label}: {$value}";
            }
        }

        $notes = $parts === []
            ? 'Screening read no details from this receipt.'
            : 'Read from the receipt — '.implode(' · ', $parts).'.';

        foreach ((array) ($assessment['concerns'] ?? []) as $concern) {
            $notes .= ' Concern: '.$concern;
        }

        return $notes.' Not verified — confirm against the bank record before activating.';
    }

    /**
     * @return array<string, mixed>
     */
    private function assess(UploadedFile $receipt, string $schoolName): array
    {
        $apiKey = config('services.anthropic.key');

        if (! $apiKey) {
            throw new \RuntimeException('ANTHROPIC_API_KEY is not configured');
        }

        $response = Http::withHeaders([
            'x-api-key' => $apiKey,
            'anthropic-version' => self::ANTHROPIC_VERSION,
        ])
            ->timeout(90)
            ->post(self::API_URL, [
                'model' => config('services.anthropic.model'),
                'max_tokens' => 1024,
                'tools' => [$this->toolSchema()],
                'tool_choice' => ['type' => 'tool', 'name' => self::TOOL_NAME],
                'messages' => [[
                    'role' => 'user',
                    'content' => [
                        $this->documentBlock($receipt),
                        ['type' => 'text', 'text' => $this->instructions($schoolName)],
                    ],
                ]],
            ]);

        if ($response->failed()) {
            $this->failFromResponse($response);
        }

        $toolUse = collect($response->json('content') ?? [])->firstWhere('type', 'tool_use');

        if (! $toolUse) {
            throw new \RuntimeException('The screening service returned no assessment');
        }

        return $toolUse['input'];
    }

    /**
     * Turn a failed screening call into an exception.
     *
     * Inlined rather than shared with document extraction: that pipeline no
     * longer calls any hosted service, and a trait named for extraction would
     * only suggest otherwise. Nothing thrown here reaches a school - screen()
     * catches it and lets the upload through for manual review.
     *
     * @throws \RuntimeException always
     */
    private function failFromResponse(Response $response): never
    {
        $message = (string) ($response->json('error.message') ?? '');

        throw new \RuntimeException(
            $message !== '' ? $message : 'the screening service returned '.$response->status()
        );
    }

    /**
     * @return array<string, mixed>
     */
    private function documentBlock(UploadedFile $receipt): array
    {
        $data = base64_encode((string) file_get_contents($receipt->getRealPath()));
        $mime = (string) $receipt->getMimeType();

        return $mime === 'application/pdf'
            ? ['type' => 'document', 'source' => ['type' => 'base64', 'media_type' => 'application/pdf', 'data' => $data]]
            : ['type' => 'image', 'source' => ['type' => 'base64', 'media_type' => $mime, 'data' => $data]];
    }

    private function instructions(string $schoolName): string
    {
        return implode("\n", [
            "A Nigerian school called \"{$schoolName}\" has uploaded this file as proof that it paid a subscription fee.",
            'Decide only whether the file IS a payment document - a bank transfer receipt, a teller slip, a mobile banking confirmation, a POS slip, or a bank statement showing the payment.',
            'Set is_payment_receipt to false for anything else: a photograph of a person or place, a blank or unreadable page, an unrelated document, a screenshot of a chat or a webpage that is not a payment confirmation.',
            'When it is not a payment document, describe in what_it_looks_like what it actually appears to be, in three or four plain words (e.g. "a photograph of a building", "a blank page").',
            'Read the amount as a plain number with no currency symbol or separators. Leave it null if you cannot read it confidently - a guess is worse than nothing here.',
            'Record anything that would make a human reviewer look twice in concerns: text that appears edited, a date far in the past, a mismatched payee. Do NOT refuse the document for these - they are notes for a person, not a verdict.',
            'You are not being asked to decide whether the payment is genuine. A person checks that against the bank record afterwards.',
            'Call the '.self::TOOL_NAME.' tool exactly once.',
        ]);
    }

    /**
     * @return array<string, mixed>
     */
    private function toolSchema(): array
    {
        return [
            'name' => self::TOOL_NAME,
            'description' => 'Record what this uploaded file is and what can be read from it.',
            'input_schema' => [
                'type' => 'object',
                'required' => ['is_payment_receipt'],
                'properties' => [
                    'is_payment_receipt' => ['type' => 'boolean'],
                    'what_it_looks_like' => ['type' => ['string', 'null']],
                    'amount' => ['type' => ['number', 'null']],
                    'paid_on' => ['type' => ['string', 'null']],
                    'reference' => ['type' => ['string', 'null']],
                    'payer' => ['type' => ['string', 'null']],
                    'payee' => ['type' => ['string', 'null']],
                    'bank' => ['type' => ['string', 'null']],
                    'concerns' => ['type' => 'array', 'items' => ['type' => 'string']],
                ],
            ],
        ];
    }
}
