<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\PaymentMethodRequest;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\AvailablePaymentMethods;
use App\Services\PaymentReceiptScreening;
use App\Services\ReceiptUploadService;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Support\Facades\Storage;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;
use Throwable;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly SubscriptionWizardService $wizard,
        private readonly ReceiptUploadService $receiptUploader,
        private readonly PaymentReceiptScreening $screening,
        private readonly AvailablePaymentMethods $available,
    ) {}

    public function create(): View|RedirectResponse
    {
        if (! $this->wizard->hasBillingDetails()) {
            return redirect()->route('subscriptions.billing-details');
        }

        $school = auth()->user()->school;

        if ($pending = $this->wizard->pendingSubscription($school)) {
            return $this->alreadySubmitted($pending);
        }

        return view('subscriptions.payment-method', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'reference' => $this->wizard->reference($school),

            // Whatever the AkademicNest Team has enabled, with the details they
            // entered. Nothing about how to pay is written into the template
            // any more, see App\Services\AvailablePaymentMethods.
            'paymentMethods' => $this->available->all(),
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        if (! $this->wizard->hasBillingDetails()) {
            return redirect()->route('subscriptions.billing-details');
        }

        $school = auth()->user()->school;

        // A payment already waiting on review: another receipt would only
        // become a second application for the same thing.
        if ($pending = $this->wizard->pendingSubscription($school)) {
            return $this->alreadySubmitted($pending);
        }

        try {
            // Screened before it is stored. This only ever turns a file away,
            // it never approves anything, and the AkademicNest Team still
            // activates every subscription by hand.
            $screening = $this->screening->screen(
                $request->file('receipt'),
                (float) $this->wizard->get('amount', 0),
                (string) $school->name,
            );

            if (! $screening['passed']) {
                return back()->withErrors(['receipt' => $screening['reason']])->withInput();
            }

            $receipt = $this->receiptUploader->store($school, $request->file('receipt'));
        } catch (ValidationException $e) {
            // Storage's own refusals, already worded for the school.
            throw $e;
        } catch (Throwable $e) {
            report($e);

            return back()->withInput()->withErrors([
                'receipt' => 'Your receipt could not be saved just now. Nothing has been submitted; please try uploading it again in a moment.',
            ]);
        }

        // A school that comes back and uploads a different receipt replaces
        // the first one; the file it replaces belongs to nothing any more.
        $previous = $this->wizard->get('receipt_path');

        $this->wizard->put([
            'payment_method' => $request->string('payment_method')->value(),
            'receipt_path' => $receipt['path'],
            'receipt_original_name' => $receipt['original_name'],
            'receipt_screening_notes' => $screening['notes'],
        ]);

        if ($previous && $previous !== $receipt['path'] && ! Payment::where('receipt_path', $previous)->exists()) {
            Storage::disk('local')->delete($previous);
        }

        return redirect()->route('subscriptions.review');
    }

    private function alreadySubmitted(Subscription $subscription): RedirectResponse
    {
        return redirect()->route('subscriptions.confirmation', $subscription)
            ->with('status', 'Your payment has already been submitted and is awaiting review by the AkademicNest Team. There is no need to submit it again.');
    }
}
