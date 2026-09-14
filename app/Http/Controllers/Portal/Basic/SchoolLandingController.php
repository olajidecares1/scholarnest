<?php

namespace App\Http\Controllers\Portal\Basic;

use App\Enums\PlanKey;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

/**
 * A Basic school's own page: akademicanest.com/greenfield-college
 *
 * This is where the school finder sends people, and it is the closest thing a
 * Basic school has to a home page. Basic does not include a public website, so
 * it is not one: it is the school's portal entry point, listing the ways to
 * sign in.
 *
 * The route behind this controller sits at the root of the site, which means a
 * school slug shares a namespace with every top-level path the application
 * owns. Two things keep that safe:
 *
 *   1. the route is registered LAST, after every other route including those
 *      in auth.php, so a real route always wins a collision; and
 *   2. slugs listed in config('basic_portal.reserved_slugs') are refused at
 *      registration, so the collision cannot be created in the first place.
 */
class SchoolLandingController extends Controller
{
    public function show(School $school): View|RedirectResponse
    {
        if ($school->hasPlanAccess(PlanKey::Basic)) {
            return view('school-portal.index', ['school' => $school]);
        }

        // Standard and Exclusive schools have their own front door, a
        // subdomain, or their own domain, and their public website lives
        // there. Send them to it rather than serving a second, competing
        // entry point from the platform root.
        //
        // websiteUrl(), not publicUrl(): a Standard school that has not
        // published a website yet has no website to redirect to, and sending
        // them to its address would bounce a visitor to a 404 by way of a
        // redirect. They get this same portal landing instead.
        if ($website = $school->websiteUrl()) {
            return redirect()->to($website);
        }

        if ($school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive)) {
            return view('school-portal.index', ['school' => $school]);
        }

        // Deactivated, unsubscribed, or awaiting approval. There is nothing
        // here to show, and 404 reveals nothing about which of those it is.
        abort(404);
    }
}
