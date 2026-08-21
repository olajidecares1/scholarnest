<?php

namespace App\Http\Controllers\Portal\Basic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\Basic\FindSchoolRequest;
use App\Models\School;
use App\Services\BasicPortalSchoolFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The Basic-plan portal's front door.
 *
 * Basic schools have no public website and no subdomain, so there is no
 * address to give out that is specific to one of them. Everyone arrives at the
 * same token-gated URL, types their school's name, and is sent on to that
 * school's own page.
 *
 * This is deliberately NOT the Standard/Exclusive flow. Those schools are
 * reached directly, at their own subdomain or their own domain, and never pass
 * through here. See docs/BASIC-PLAN-PORTAL.md.
 */
class SchoolFinderController extends Controller
{
    /**
     * Show the "Enter your school name" form.
     */
    public function show(string $token): View
    {
        return view('portal.basic.finder', [
            'token' => $token,
            'matches' => collect(),
        ]);
    }

    /**
     * Resolve the typed name to a school and send the visitor there.
     *
     * Three outcomes:
     *
     *   one match    - redirect straight to that school's page
     *   several      - show them to choose from, rather than guessing
     *   none         - say so, without hinting whether the name exists on
     *                  another plan
     */
    public function find(FindSchoolRequest $request, BasicPortalSchoolFinder $finder, string $token): View|RedirectResponse
    {
        $matches = $finder->search($request->searchTerm());

        if ($matches->count() === 1) {
            /** @var School $school */
            $school = $matches->first();

            return redirect()->route('basic-portal.school', $school->slug);
        }

        if ($matches->isEmpty()) {
            return back()
                ->withInput()
                ->withErrors([
                    // Deliberately says nothing about why. Confirming that a
                    // name exists but is on another plan would leak both the
                    // school's existence and what it pays for.
                    'school' => "We couldn't find a school with that name. Please check the spelling, or ask your school for the exact name they registered with.",
                ]);
        }

        return view('portal.basic.finder', [
            'token' => $token,
            'matches' => $matches,
            'term' => $request->searchTerm(),
        ]);
    }
}
