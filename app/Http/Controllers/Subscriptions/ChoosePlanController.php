<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\BillingCycle;
use App\Enums\PlanKey;
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

        if ($plan->key === PlanKey::Exclusive) {
            return redirect()->route('subscriptions.contact-sales');
        }

        if ($plan->key === PlanKey::Basic) {
            $studentsCount = $request->integer('students_count');

            $this->wizard->put([
                'plan_id' => $plan->id,
                'billing_cycle' => BillingCycle::PerStudentPerTerm->value,
                'students_count' => $studentsCount,
                'amount' => (float) $plan->price_per_student_per_term * $studentsCount,
            ]);
        } else {
            $billingCycle = BillingCycle::from($request->string('billing_cycle')->value());

            $this->wizard->put([
                'plan_id' => $plan->id,
                'billing_cycle' => $billingCycle->value,
                'students_count' => null,
                'amount' => $billingCycle === BillingCycle::Monthly
                    ? (float) $plan->price_monthly
                    : (float) $plan->price_per_term,
            ]);
        }

        return redirect()->route('subscriptions.billing-details');
    }
}
