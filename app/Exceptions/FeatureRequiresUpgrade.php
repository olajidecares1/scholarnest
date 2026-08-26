<?php

namespace App\Exceptions;

use App\Enums\PlanFeature;
use Symfony\Component\HttpKernel\Exception\HttpException;

/**
 * A school reached a feature its plan does not include.
 *
 * Still a 403 on the wire - the refusal is real, and anything that checks the
 * status code (a fetch, a test, a log) should see it as one. What changes is
 * what the person sees: this carries the feature with it, so the handler can
 * render a page that names what they wanted, says which plan includes it, and
 * always offers a way back to the dashboard.
 *
 * A bare abort(403, 'CBT requires the Standard or Exclusive plan.') could not
 * do that. It produced a message with nowhere to go.
 */
class FeatureRequiresUpgrade extends HttpException
{
    public function __construct(public readonly PlanFeature $feature)
    {
        parent::__construct(
            403,
            "{$feature->label()} requires {$feature->requiredPlanLabel()}.",
        );
    }
}
