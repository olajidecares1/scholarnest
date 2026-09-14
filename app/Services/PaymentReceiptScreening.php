<?php

namespace App\Services;

use App\Services\DocumentExtraction\PdfTextExtractor;
use Carbon\CarbonImmutable;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Log;
use RuntimeException;
use Throwable;

/**
 * A doorman for payment receipts, not an auditor.
 *
 * This exists to stop the obvious: a blank page, an image too small to read, a
 * document that is plainly not a payment, a receipt for far less than the plan
 * costs. It turns those away at the door with a reason the school can act on.
 *
 * Everything happens on AkademicNest's own servers. The receipt is never sent to
 * an outside service: a PDF's text is read locally, and an image is checked for
 * being readable at all.
 *
 * What it deliberately does NOT do is approve anything. A document that passes
 * every check here is still only "worth a person looking at": it is stored,
 * the school is told it is under review, and the AkademicNest Team activates the
 * subscription by hand. Nothing in this class shortens that.
 *
 * The reason for that line is simple: a carefully edited receipt will pass
 * every check that can be written. If the system announced such a document as
 * "verified", whoever reviews payments would reasonably stop looking, and that
 * is exactly the moment a forgery gets through. Screening that only ever
 * rejects makes the review easier without ever making it feel unnecessary.
 */
class PaymentReceiptScreening
{
    /**
     * How far below the required amount is still let through to a person.
     *
     * Not zero, because an amount read off a document is not always exact, and
     * a school that genuinely paid should never be turned away by a rounding
     * difference. Only a clear, large shortfall is refused outright.
     */
    private const AMOUNT_TOLERANCE = 0.02;

    /**
     * An image whose shorter side is below this cannot show a readable amount,
     * date and reference.
     */
    private const MIN_IMAGE_SIDE = 120;

    /**
     * The spread of brightness, from the darkest to the lightest part of the
     * image, below which it is treated as blank. Any text, photograph or
     * screenshot spreads far wider than this.
     */
    private const BLANK_IMAGE_SPREAD = 20;

    /**
     * Words that appear on bank transfer receipts, teller slips, POS slips,
     * mobile banking confirmations and statements.
     */
    private const PAYMENT_TERMS = [
        'amount', 'transfer', 'transaction', 'payment', 'paid', 'debit', 'credit',
        'receipt', 'reference', 'beneficiary', 'account', 'teller', 'deposit',
        'narration', 'remark', 'session id', 'successful', 'naira', 'ngn', '₦',
        'bank', 'pos', 'sender', 'recipient', 'remitter', 'balance', 'value date',
    ];

    private const BANKS = [
        'Access Bank', 'Citibank', 'Ecobank', 'Fidelity Bank', 'First Bank', 'FirstBank', 'FCMB',
        'First City Monument Bank', 'Globus Bank', 'GTBank', 'GTCO', 'Guaranty Trust', 'Heritage Bank',
        'Jaiz Bank', 'Keystone Bank', 'Kuda', 'Lotus Bank', 'Moniepoint', 'OPay', 'PalmPay',
        'Parallex Bank', 'Polaris Bank', 'Premium Trust', 'Providus Bank', 'Stanbic IBTC', 'Sterling Bank',
        'SunTrust Bank', 'Taj Bank', 'Titan Trust', 'Union Bank', 'United Bank for Africa', 'UBA',
        'Unity Bank', 'Wema Bank', 'ALAT', 'Zenith Bank',
    ];

    public function __construct(private readonly PdfTextExtractor $pdf) {}

    /**
     * Look at a receipt before it is accepted.
     *
     * @param  float  $requiredAmount  What the plan or top-up actually costs.
     * @return array{passed: bool, reason: ?string, notes: ?string}
     */
    public function screen(UploadedFile $receipt, float $requiredAmount, string $schoolName): array
    {
        try {
            $assessment = $this->isPdf($receipt)
                ? $this->assessPdf($receipt, $schoolName)
                : $this->assessImage($receipt);
        } catch (Throwable $e) {
            // Something unexpected went wrong while reading the file. A school
            // that has paid must not be blocked by our own fault, so the upload
            // goes through and the reviewer is told the check never ran.
            Log::warning('Payment receipt screening did not run: '.$e->getMessage());

            return [
                'passed' => true,
                'reason' => null,
                'notes' => 'Automatic screening did not run ('.$e->getMessage().'). Review this receipt manually.',
            ];
        }

        if ($assessment['refusal'] !== null) {
            return ['passed' => false, 'reason' => $assessment['refusal'], 'notes' => null];
        }

        $shortfall = $this->shortfall($assessment['amounts'], $requiredAmount);

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

        // Nothing here rules it out. That is as far as this goes: the note is
        // what the reviewer sees, and it does not say "verified".
        return [
            'passed' => true,
            'reason' => null,
            'notes' => $this->reviewerNotes($assessment, $requiredAmount),
        ];
    }

    private function isPdf(UploadedFile $receipt): bool
    {
        return $receipt->getMimeType() === 'application/pdf'
            || strtolower($receipt->getClientOriginalExtension()) === 'pdf';
    }

    /**
     * @return array<string, mixed>
     */
    private function assessImage(UploadedFile $receipt): array
    {
        $path = (string) $receipt->getRealPath();
        $size = @getimagesize($path);

        if ($size === false) {
            return $this->refused('Payment Verification Failed. The image you uploaded could not be opened. '.$this->whatToUpload());
        }

        [$width, $height] = $size;

        if (min($width, $height) < self::MIN_IMAGE_SIDE) {
            return $this->refused('Payment Verification Failed. The image you uploaded is too small to read. '.$this->whatToUpload());
        }

        if ($this->looksBlank($path)) {
            return $this->refused('Payment Verification Failed. The image you uploaded appears to be blank. '.$this->whatToUpload());
        }

        return $this->assessment(kind: 'image');
    }

    /**
     * Whether an image has no visible content at all.
     *
     * Measured on a reduced copy, so a large photograph costs no more than a
     * small one. The darkest and lightest one percent are ignored, so a speck
     * of dust or a single stray pixel does not count as content.
     */
    private function looksBlank(string $path): bool
    {
        $source = @imagecreatefromstring((string) file_get_contents($path));

        if ($source === false) {
            throw new RuntimeException('the image could not be decoded');
        }

        $width = imagesx($source);
        $height = imagesy($source);
        $scale = min(1, 256 / max($width, $height));
        $sampleWidth = max(1, (int) round($width * $scale));
        $sampleHeight = max(1, (int) round($height * $scale));

        $sample = imagecreatetruecolor($sampleWidth, $sampleHeight);
        imagecopyresampled($sample, $source, 0, 0, 0, 0, $sampleWidth, $sampleHeight, $width, $height);
        imagedestroy($source);

        $levels = [];

        for ($y = 0; $y < $sampleHeight; $y++) {
            for ($x = 0; $x < $sampleWidth; $x++) {
                $rgb = imagecolorat($sample, $x, $y);
                $levels[] = (int) round(0.299 * (($rgb >> 16) & 0xFF) + 0.587 * (($rgb >> 8) & 0xFF) + 0.114 * ($rgb & 0xFF));
            }
        }

        imagedestroy($sample);
        sort($levels);

        $last = count($levels) - 1;
        $dark = $levels[(int) floor($last * 0.01)];
        $light = $levels[(int) ceil($last * 0.99)];

        return ($light - $dark) < self::BLANK_IMAGE_SPREAD;
    }

    /**
     * @return array<string, mixed>
     */
    private function assessPdf(UploadedFile $receipt, string $schoolName): array
    {
        $document = $this->pdf->extract((string) $receipt->getRealPath());
        $text = trim($document->text);

        $lower = mb_strtolower($text);
        $terms = array_filter(self::PAYMENT_TERMS, fn (string $term) => $this->mentions($lower, $term));

        // A scanned PDF has little or no text to read, and many banks send
        // statements as password protected PDFs. Neither is a reason to turn a
        // school away. A short receipt that does name a payment is still read.
        if ($text === '' || ($document->looksScanned && count($terms) < 2)) {
            return $this->assessment(kind: 'unreadable_pdf');
        }

        if (count($terms) < 2) {
            return $this->refused('Payment Verification Failed. The PDF you uploaded does not look like a proof of payment. '.$this->whatToUpload());
        }

        $paidOn = $this->date($text);
        $concerns = [];

        if ($paidOn !== null && $paidOn->lessThan(CarbonImmutable::now()->subMonths(6))) {
            $concerns[] = 'The date on the receipt is more than six months old.';
        }

        if ($paidOn !== null && $paidOn->greaterThan(CarbonImmutable::now()->addDay())) {
            $concerns[] = 'The date on the receipt is in the future.';
        }

        return $this->assessment(
            kind: 'pdf',
            amounts: $this->amounts($text),
            paidOn: $paidOn?->format('j M Y'),
            reference: $this->reference($text),
            bank: $this->bank($text),
            mentionsSchool: $schoolName !== '' && str_contains($lower, mb_strtolower($schoolName)),
            concerns: $concerns,
        );
    }

    private function mentions(string $lowerText, string $term): bool
    {
        // Short words like "pos" must stand alone, or "purpose" would count.
        if (mb_strlen($term) <= 4 && preg_match('/^[a-z ]+$/', $term)) {
            return (bool) preg_match('/\b'.preg_quote($term, '/').'\b/u', $lowerText);
        }

        return str_contains($lowerText, $term);
    }

    /**
     * Every amount the document states as money: after ₦, NGN or N, or on the
     * same line as a word such as "amount" or "total".
     *
     * @return list<float>
     */
    private function amounts(string $text): array
    {
        $number = '([0-9]{1,3}(?:,[0-9]{3})+(?:\.[0-9]{1,2})?|[0-9]+(?:\.[0-9]{1,2})?)';

        preg_match_all('/(?:₦|\bNGN|(?<![A-Za-z])N)\s?'.$number.'/u', $text, $currency);
        preg_match_all('/\b(?:amount|total|sum|paid)\b[^0-9\n]{0,25}'.$number.'/iu', $text, $labelled);

        return collect([...$currency[1], ...$labelled[1]])
            ->map(fn (string $value) => (float) str_replace(',', '', $value))
            ->filter(fn (float $value) => $value >= 1)
            ->values()
            ->all();
    }

    private function reference(string $text): ?string
    {
        $pattern = '/\b(?:transaction\s+(?:reference|ref|id)|reference(?:\s+(?:no|number|id))?|ref(?:\s+no)?|session\s+id)\b\.?\s*[:#]?\s*([A-Z0-9][A-Z0-9\/\-]{5,})/i';

        return preg_match($pattern, $text, $match) ? $match[1] : null;
    }

    private function bank(string $text): ?string
    {
        foreach (self::BANKS as $bank) {
            if (preg_match('/\b'.preg_quote($bank, '/').'\b/i', $text)) {
                return $bank;
            }
        }

        return null;
    }

    /**
     * The first date the document shows, in a format Nigerian banks use.
     */
    private function date(string $text): ?CarbonImmutable
    {
        $month = '(?:Jan|Feb|Mar|Apr|May|Jun|Jul|Aug|Sep|Sept|Oct|Nov|Dec)[a-z]*';

        $patterns = [
            ['/\b(\d{1,2}\/\d{1,2}\/\d{4})\b/', 'd/m/Y'],
            ['/\b(\d{4}-\d{2}-\d{2})\b/', 'Y-m-d'],
            ['/\b(\d{1,2}(?:st|nd|rd|th)?[ \-]'.$month.'[ \-,]+\d{4})\b/i', null],
            ['/\b('.$month.' \d{1,2},? \d{4})\b/i', null],
        ];

        foreach ($patterns as [$pattern, $format]) {
            if (! preg_match($pattern, $text, $match)) {
                continue;
            }

            try {
                $value = preg_replace('/(\d)(st|nd|rd|th)\b/i', '$1', $match[1]) ?? $match[1];

                return $format !== null
                    ? CarbonImmutable::createFromFormat('!'.$format, $value)
                    : CarbonImmutable::parse(str_replace('-', ' ', $value))->startOfDay();
            } catch (Throwable) {
                continue;
            }
        }

        return null;
    }

    /**
     * A shortfall worth refusing over, or null.
     *
     * Only when every amount found is clearly short. A statement that also
     * shows a larger balance is not refused, and no amount at all is not a
     * refusal either: a person reads what could not be read here.
     *
     * @param  list<float>  $amounts
     * @return array{found: float, required: float}|null
     */
    private function shortfall(array $amounts, float $requiredAmount): ?array
    {
        if ($amounts === [] || $requiredAmount <= 0) {
            return null;
        }

        $found = max($amounts);

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
    private function reviewerNotes(array $assessment, float $requiredAmount): string
    {
        $notes = match ($assessment['kind']) {
            'image' => 'Image receipt: read the amount, date and reference from the image.',
            'unreadable_pdf' => 'This PDF has no readable text (it may be scanned or password protected). Read it manually.',
            default => $this->readFromPdf($assessment, $requiredAmount),
        };

        foreach ($assessment['concerns'] as $concern) {
            $notes .= ' Concern: '.$concern;
        }

        return $notes.' Not verified. Confirm against the bank record before activating.';
    }

    /**
     * @param  array<string, mixed>  $assessment
     */
    private function readFromPdf(array $assessment, float $requiredAmount): string
    {
        $parts = [];

        if ($assessment['amounts'] !== []) {
            $matching = collect($assessment['amounts'])
                ->first(fn (float $amount) => $requiredAmount > 0 && $amount >= $requiredAmount * (1 - self::AMOUNT_TOLERANCE));

            $parts[] = 'Amount: ₦'.number_format($matching ?? max($assessment['amounts']), 2);
        }

        foreach (['paidOn' => 'Date', 'reference' => 'Reference', 'bank' => 'Bank'] as $key => $label) {
            if ($assessment[$key] !== null) {
                $parts[] = "{$label}: {$assessment[$key]}";
            }
        }

        if ($assessment['mentionsSchool']) {
            $parts[] = "Mentions the school's name";
        }

        return $parts === []
            ? 'Screening read no payment details from this PDF.'
            : 'Read from the receipt: '.implode(' · ', $parts).'.';
    }

    private function whatToUpload(): string
    {
        return 'Please upload a clear photo, screenshot or PDF of your bank transfer receipt or teller, '
            .'showing the amount paid, the date and the transaction reference.';
    }

    /**
     * @return array<string, mixed>
     */
    private function refused(string $reason): array
    {
        return [...$this->assessment(kind: 'refused'), 'refusal' => $reason];
    }

    /**
     * @param  list<float>  $amounts
     * @param  list<string>  $concerns
     * @return array<string, mixed>
     */
    private function assessment(
        string $kind,
        array $amounts = [],
        ?string $paidOn = null,
        ?string $reference = null,
        ?string $bank = null,
        bool $mentionsSchool = false,
        array $concerns = [],
    ): array {
        return [
            'kind' => $kind,
            'refusal' => null,
            'amounts' => $amounts,
            'paidOn' => $paidOn,
            'reference' => $reference,
            'bank' => $bank,
            'mentionsSchool' => $mentionsSchool,
            'concerns' => $concerns,
        ];
    }
}
