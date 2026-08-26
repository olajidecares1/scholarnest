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

        // A per-student plan has no amount until the school has said how many
        // students it is onboarding. Reaching this screen with that unanswered
        // means the step was jumped by URL, so send it back rather than let it
        // carry a null count all the way to the receipt.
        if ($this->wizard->isPerStudent() && ! $this->wizard->hasStudentCapacity()) {
            return redirect()->route('subscriptions.students');
        }

        $school = auth()->user()->school;

        return view('subscriptions.billing-details', [
            'plan' => Plan::findOrFail($this->wizard->get('plan_id')),
            'backRoute' => $this->wizard->isPerStudent()
                ? route('subscriptions.students')
                : route('subscriptions.choose-plan'),
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

        if ($this->wizard->isPerStudent() && ! $this->wizard->hasStudentCapacity()) {
            return redirect()->route('subscriptions.students');
        }

        $validated = $request->validated();

        $this->wizard->put($validated);

        auth()->user()->school->update($validated);

        return redirect()->route('subscriptions.payment-method');
    }
}
