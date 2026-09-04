<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdatePlanPricingRequest;
use App\Models\AuditLog;
use App\Models\Plan;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * Lets the Super Admin change what a plan costs.
 *
 * The Basic plan's per-student price is the number the whole student-licence
 * system multiplies by, so it has to be adjustable without a developer and a
 * deployment. It was already a database column rather than a constant, but
 * until now there was no way to reach it except by editing the table by hand.
 *
 * Changing a price never alters a payment already submitted: each top-up
 * snapshots the price it was quoted at (see subscription_top_ups
 * .price_per_student), so a school is always charged what it was shown.
 */
class PlanPricingController extends Controller
{
    public function edit(): View
    {
        return view('super-admin.plans.pricing', [
            'plans' => Plan::orderBy('sort_order')->get(),
        ]);
    }

    public function update(UpdatePlanPricingRequest $request, Plan $plan): RedirectResponse
    {
        $before = [
            'price_per_student_per_term' => $plan->price_per_student_per_term,
            'price_monthly' => $plan->price_monthly,
            'price_per_term' => $plan->price_per_term,
        ];

        $plan->update($request->validated());

        AuditLog::record(
            'plan.pricing.updated',
            // Basic AND Standard are both per-student now, so both get the
            // specific before/after line rather than the vague one.
            $plan->key->isSoldPerStudent()
                ? sprintf(
                    'Changed the %s per-student price from %s to %s.',
                    $plan->name,
                    number_format((float) $before['price_per_student_per_term'], 2),
                    number_format((float) $plan->price_per_student_per_term, 2),
                )
                : sprintf('Updated pricing for the %s plan.', $plan->name),
            $plan,
        );

        return back()->with('status', "Pricing for the {$plan->name} plan was updated.");
    }
}
