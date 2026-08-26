<?php

namespace App\Http\Middleware;

use App\Enums\PlanFeature;
use App\Exceptions\FeatureRequiresUpgrade;
use App\Http\Middleware\Concerns\ResolvesRequestSchool;
use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * One gate for every plan-restricted feature: plan_feature:cbt, and so on.
 *
 * This replaces four near-identical middleware classes, each of which held its
 * own copy of the same three lines and its own wording. Four copies is how the
 * rule drifted: some features were gated, some were not, and the ones that were
 * refused in four different sentences.
 *
 * It resolves the school through the acting guard, so the same gate holds on
 * the staff portal as on the School Admin panel. That matters here more than it
 * looks: the staff portal is open to Basic schools, so its premium corners are
 * reachable by a Basic teacher unless something stops them. A gate that only
 * understood the default guard would have let every teacher through.
 */
class EnsureSchoolHasFeature
{
    use ResolvesRequestSchool;

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     *
     * @throws FeatureRequiresUpgrade
     */
    public function handle(Request $request, Closure $next, string $feature): Response
    {
        $planFeature = PlanFeature::from($feature);
        $school = $this->resolveSchool($request);

        if (! $school?->hasPlanAccess(...$planFeature->requiredPlans())) {
            throw new FeatureRequiresUpgrade($planFeature);
        }

        return $next($request);
    }
}
