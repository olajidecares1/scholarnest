<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\BillingCycle;
use App\Http\Controllers\Controller;
use App\Http\Requests\Subscriptions\ChoosePlanRequest;
use App\Models\Plan;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class ChoosePlanController extends Controller
{
    public function __construct(private readonly SubscriptionWizardService $wizard) {}

    public function create(): View
    {
        $plans = Plan::orderBy('sort_order')->get();

        return view('subscriptions.choose-plan', [
            'plans' => $plans,
            'selectedPlanId' => $this->wizard->get('plan_id'),
        ]);
    }

    public function store(ChoosePlanRequest $request): RedirectResponse
    {
        $plan = Plan::findOrFail($request->integer('plan_id'));

        // Exclusive is Coming Soon. Refused HERE as well as hidden on the
        // page, because a disabled radio button is a suggestion and a posted
        // plan_id is not, anything that only stopped the click would let a
        // school onto a plan nobody is ready to sell by editing the form.
        if (! $plan->key->isAvailableToSubscribe()) {
            return back()->withErrors([
                'plan_id' => $plan->key->label().' is coming soon and cannot be subscribed to yet.',
            ]);
        }

        // Basic AND Standard: both priced per student now, so both ask the
        // quantity on its own step next and only the plan is settled here.
        // Anything already entered is left alone, so stepping back to change
        // plan and returning does not silently wipe the number a school typed.
        $this->wizard->put([
            'plan_id' => $plan->id,
            'billing_cycle' => BillingCycle::PerStudentPerTerm->value,
        ]);

        return redirect()->route('subscriptions.students');
    }
}
