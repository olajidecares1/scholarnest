<?php

namespace App\Http\Controllers\Portal\Basic;

use App\Http\Controllers\Controller;
use App\Http\Requests\Portal\Basic\FindSchoolRequest;
use App\Models\School;
use App\Services\PortalSchoolFinder;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * The portal's front door, for every plan.
 *
 * Name your school, land on that school's portal hub, pick the portal you
 * need, and which portals are offered there is decided by the school's plan,
 * on the hub itself, not here.
 *
 * It began as the Basic-plan entry point, which is why it still lives under
 * Portal\Basic and answers on the token-gated address as well as the open
 * /portal one. Basic schools have no website and no subdomain, so a finder was
 * the only way to reach them; it turned out to be the way every school wants
 * to arrive, including ones that have a subdomain and have simply mislaid it.
 *
 * The original reasoning, still true of Basic specifically:
 *
 * Basic schools have no public website and no subdomain, so there is no
 * address to give out that is specific to one of them. Everyone arrives at the
 * same token-gated URL, types their school's name, and is sent on to that
 * school's own page.
 *
 * Standard and Exclusive schools are ALSO reached directly, at their own
 * subdomain or their own domain. That remains the better address when
 * somebody has it, this is the fallback for everybody who does not.
 * See docs/BASIC-PLAN-PORTAL.md.
 */
class SchoolFinderController extends Controller
{
    /**
     * Show the "Enter your school name" form.
     *
     * $token is null at /portal, the open front door, and set on the older
     * token-gated address. Both render the same form; the token only decides
     * where it posts back to.
     */
    public function show(?string $token = null): View
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
     *   one match, redirect straight to that school's page
     *   several, show them to choose from, rather than guessing
     *   none, say so, without hinting whether the name exists on
     *                  another plan
     */
    public function find(FindSchoolRequest $request, PortalSchoolFinder $finder, ?string $token = null): View|RedirectResponse
    {
        $matches = $finder->search($request->searchTerm());

        if ($matches->count() === 1) {
            /** @var School $school */
            $school = $matches->first();

            // The PORTAL HUB, not the school landing page.
            //
            // basic-portal.school forwards a Standard or Exclusive school to
            // its public website, which is right when somebody is looking for
            // the school and wrong when they have just asked for its portal:
            // a teacher typing their school's name would land on the school's
            // marketing site instead of the sign-in they wanted.
            //
            // The hub renders for every plan and shows exactly the portals
            // that plan includes.
            //
            // The MODEL, not the slug. The route binds on portal_key, so
            // passing a slug string builds an address that no longer matches,
            // and one that would name the school if it did.
            return redirect()->route('portal.index', $school);
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
