<?php

namespace App\Http\Controllers\Subscriptions;

use App\Enums\BillingCycle;
use App\Enums\PlanKey;
use App\Http\Controllers\Controller;
use App\Models\Plan;
use App\Services\SubscriptionWizardService;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * How many students the school is onboarding, and what that costs.
 *
 * A step of its own rather than a field tucked onto the plan cards. For a
 * Basic school this number IS the subscription - it decides the price and
 * becomes the capacity the school is held to for the rest of the term - so it
 * gets its own place in the progress bar and its own screen to be checked on.
 *
 * Only Basic reaches here. Standard is a flat fee with no per-student
 * component, so the step does not exist for it and the bar does not show it.
 */
class StudentCapacityController extends Controller
{
    public function __construct(private readonly SubscriptionWizardService $wizard) {}

    public function create(): View|RedirectResponse
    {
        $plan = Plan::find($this->wizard->get('plan_id'));

        if (! $plan) {
            return redirect()->route('subscriptions.choose-plan');
        }

        // Nothing to ask a flat-fee plan, and no step to show it.
        if ($plan->key !== PlanKey::Basic) {
            return redirect()->route('subscriptions.billing-details');
        }

        return view('subscriptions.students', [
            'plan' => $plan,
            'studentsCount' => $this->wizard->get('students_count'),
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $plan = Plan::find($this->wizard->get('plan_id'));

        if (! $plan) {
            return redirect()->route('subscriptions.choose-plan');
        }

        if ($plan->key !== PlanKey::Basic) {
            return redirect()->route('subscriptions.billing-details');
        }

        $validated = $request->validate([
            'students_count' => ['required', 'integer', 'min:1', 'max:100000'],
        ], [
            'students_count.required' => 'Please tell us how many students you are onboarding.',
            'students_count.min' => 'You need at least one student space.',
        ]);

        $studentsCount = (int) $validated['students_count'];

        // Priced from the plan record, never from anything the browser sent.
        // The figure the school was shown is a quote; this is the charge.
        $this->wizard->put([
            'billing_cycle' => BillingCycle::PerStudentPerTerm->value,
            'students_count' => $studentsCount,
            'amount' => (float) $plan->price_per_student_per_term * $studentsCount,
        ]);

        return redirect()->route('subscriptions.billing-details');
    }
}
