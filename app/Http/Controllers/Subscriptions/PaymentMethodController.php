<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\PaymentMethodRequest;
use App\Models\Plan;
use App\Services\ReceiptUploadService;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class PaymentMethodController extends Controller
{
    public function __construct(
        private readonly SubscriptionWizardService $wizard,
        private readonly ReceiptUploadService $receiptUploader,
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
        ]);
    }

    public function store(PaymentMethodRequest $request): RedirectResponse
    {
        if (! $this->wizard->hasBillingDetails()) {
            return redirect()->route('subscriptions.billing-details');
        }

        $school = auth()->user()->school;
        $receipt = $this->receiptUploader->store($school, $request->file('receipt'));

        $this->wizard->put([
            'payment_method' => $request->string('payment_method')->value(),
            'receipt_path' => $receipt['path'],
            'receipt_original_name' => $receipt['original_name'],
        ]);

        return redirect()->route('subscriptions.review');
    }
}
