<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\PaymentMethodRequest;
use App\Models\Plan;
use App\Services\AvailablePaymentMethods;
use App\Services\PaymentReceiptScreening;
use App\Services\ReceiptUploadService;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

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

        return view('subscriptions.payment-method', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'reference' => $this->wizard->reference($school),

            // Whatever the EduNest Team has enabled, with the details they
            // entered. Nothing about how to pay is written into the template
            // any more - see App\Services\AvailablePaymentMethods.
            'paymentMethods' => $this->available->all(),
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        if (! $this->wizard->hasBillingDetails()) {
            return redirect()->route('subscriptions.billing-details');
        }

        $school = auth()->user()->school;

        // Screened before it is stored. This only ever turns a file away - it
        // never approves anything, and the EduNest Team still activates every
        // subscription by hand.
        $screening = $this->screening->screen(
            $request->file('receipt'),
            (float) $this->wizard->get('amount', 0),
            (string) $school->name,
        );

        if (! $screening['passed']) {
            return back()->withErrors(['receipt' => $screening['reason']])->withInput();
        }

        $receipt = $this->receiptUploader->store($school, $request->file('receipt'));

        $this->wizard->put([
            'payment_method' => $request->string('payment_method')->value(),
            'receipt_path' => $receipt['path'],
            'receipt_original_name' => $receipt['original_name'],
            'receipt_screening_notes' => $screening['notes'],
        ]);

        return redirect()->route('subscriptions.review');
    }
}
