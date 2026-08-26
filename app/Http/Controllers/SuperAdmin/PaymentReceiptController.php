<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\Subscription;
use App\Models\SubscriptionTopUp;
use Illuminate\Support\Facades\Storage;
use Symfony\Component\HttpFoundation\StreamedResponse;

/**
 * Serves an uploaded payment receipt to the Super Admin.
 *
 * Receipts are deliberately stored on the private "local" disk rather than in
 * public storage. A receipt is a financial document carrying a school's bank
 * details and reference numbers, and anything under public/storage is readable
 * by anyone who can guess or discover the filename.
 *
 * So they are streamed through this controller instead, which runs behind the
 * super_admin middleware. That is also what makes "verify the payment" a real
 * instruction rather than an act of faith: without it the Super Admin is asked
 * to confirm an amount they have no way of seeing.
 */
class PaymentReceiptController extends Controller
{
    /**
     * The receipt uploaded with a school's original subscription payment.
     *
     * The counterpart to showTopUpReceipt() below, which only ever covered
     * top-ups. Without this the Super Admin had nowhere inside their own panel
     * to actually look at what a school had paid, which is why reviewing a
     * subscription meant opening the school's signup pages instead.
     */
    public function showSubscriptionReceipt(Subscription $subscription): StreamedResponse
    {
        $payment = $subscription->latestPayment;

        abort_if($payment === null || blank($payment->receipt_path), 404, 'No receipt was uploaded for this subscription.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($payment->receipt_path), 404, 'This receipt is no longer on file.');

        return $disk->response(
            $payment->receipt_path,
            $payment->receipt_original_name ?: 'receipt',
            [
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; object-src 'self'",
                'Content-Disposition' => 'inline; filename="'.addslashes($payment->receipt_original_name ?: 'receipt').'"',
            ],
        );
    }

    public function showTopUpReceipt(SubscriptionTopUp $topUp): StreamedResponse
    {
        abort_if(blank($topUp->receipt_path), 404, 'No receipt was uploaded for this request.');

        $disk = Storage::disk('local');

        abort_unless($disk->exists($topUp->receipt_path), 404, 'This receipt is no longer on file.');

        // Shown in the browser rather than downloaded, so verifying a payment
        // does not litter the reviewer's machine with copies of other people's
        // financial documents.
        return $disk->response(
            $topUp->receipt_path,
            $topUp->receipt_original_name ?: 'receipt',
            [
                // The file came from an untrusted upload. nosniff stops the
                // browser deciding a "receipt" is really HTML and running it,
                // and the sandbox neuters anything a crafted PDF might try.
                'X-Content-Type-Options' => 'nosniff',
                'Content-Security-Policy' => "default-src 'none'; img-src 'self'; object-src 'self'",
                'Content-Disposition' => 'inline; filename="'.addslashes($topUp->receipt_original_name ?: 'receipt').'"',
            ],
        );
    }
}
