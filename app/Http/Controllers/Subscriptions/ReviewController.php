<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\BillingCycle;
use App\Enums\PaymentMethod;
use App\Enums\PaymentStatus;
use App\Enums\SubscriptionStatus;
use App\Http\Controllers\Controller;
use App\Models\Payment;
use App\Models\Plan;
use App\Models\Subscription;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ReviewController extends Controller
{
    public function __construct(private readonly SubscriptionWizardService $wizard) {}

    public function create(): View|RedirectResponse
    {
        if (! $this->wizard->hasPaymentMethod()) {
            return redirect()->route('subscriptions.payment-method');
        }

        $school = auth()->user()->school;

        return view('subscriptions.review', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'billingCycle' => BillingCycle::from($this->wizard->get('billing_cycle')),
            'studentsCount' => $this->wizard->get('students_count'),
            'amount' => (float) $this->wizard->get('amount'),
            'billingContactName' => $this->wizard->get('billing_contact_name'),
            'billingEmail' => $this->wizard->get('billing_email'),
            'billingPhone' => $this->wizard->get('billing_phone'),
            'billingAddress' => $this->wizard->get('billing_address'),
            'paymentMethod' => PaymentMethod::from($this->wizard->get('payment_method')),
            'receiptOriginalName' => $this->wizard->get('receipt_original_name'),
            'reference' => $this->wizard->reference($school),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        if (! $this->wizard->hasPaymentMethod()) {
            return redirect()->route('subscriptions.payment-method');
        }

        $school = auth()->user()->school;
        $data = $this->wizard->data();

        $subscription = DB::transaction(function () use ($school, $data) {
            $subscription = Subscription::create([
                'school_id' => $school->id,
                'plan_id' => $data['plan_id'],
                'billing_cycle' => $data['billing_cycle'],
                'students_count' => $data['students_count'] ?? null,
                'amount' => $data['amount'],
                'currency' => 'NGN',
                'status' => SubscriptionStatus::PendingVerification,
                'reference' => $data['reference'],
            ]);

            Payment::create([
                'subscription_id' => $subscription->id,
                'method' => $data['payment_method'],
                'amount' => $data['amount'],
                'currency' => 'NGN',
                'status' => PaymentStatus::Pending,
                'reference' => strtoupper((string) Str::random(10)),
                'receipt_path' => $data['receipt_path'],
                'receipt_original_name' => $data['receipt_original_name'],
            ]);

            return $subscription;
        });

        $this->wizard->clear();

        return redirect()->route('subscriptions.confirmation', $subscription);
    }
}
