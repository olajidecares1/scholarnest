<?php

namespace App\Http\Controllers\Subscriptions;

use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\BillingDetailsRequest;
use App\Models\Plan;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class BillingDetailsController extends Controller
{
    public function __construct(private readonly SubscriptionWizardService $wizard) {}

    public function create(): View|RedirectResponse
    {
        if (! $this->wizard->hasChosenPlan()) {
            return redirect()->route('subscriptions.choose-plan');
        }

        $school = auth()->user()->school;

        return view('subscriptions.billing-details', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'billingContactName' => $this->wizard->get('billing_contact_name', $school->billing_contact_name ?? $school->name),
            'billingEmail' => $this->wizard->get('billing_email', $school->billing_email ?? auth()->user()->email),
            'billingPhone' => $this->wizard->get('billing_phone', $school->billing_phone),
            'billingAddress' => $this->wizard->get('billing_address', $school->billing_address),
        ]);
    }

    public function store(BillingDetailsRequest $request): RedirectResponse
    {
        if (! $this->wizard->hasChosenPlan()) {
            return redirect()->route('subscriptions.choose-plan');
        }

        $validated = $request->validated();

        $this->wizard->put($validated);

        auth()->user()->school->update($validated);

        return redirect()->route('subscriptions.payment-method');
    }
}
