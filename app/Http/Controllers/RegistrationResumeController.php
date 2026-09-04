<?php

namespace App\Http\Controllers;

use App\Models\School;
use Illuminate\Http\RedirectResponse;

/**
 * "Continue where you left off" - the link in the reminder email.
 *
 * IT DOES NOT SIGN ANYBODY IN. A signature on a URL proves the link came from
 * us; it proves nothing about who is holding it. Billing emails get forwarded
 * to bursars, quoted in tickets and left open on shared machines, and a link
 * that logged its holder in as the school's administrator would make every one
 * of those a way into the account.
 *
 * So it does the one useful thing it safely can: it lands the visitor at the
 * right step. Somebody already signed in as that school goes straight there;
 * anybody else meets the normal sign-in page, and arrives at the same step
 * afterwards.
 */
class RegistrationResumeController extends Controller
{
    public function __invoke(School $school): RedirectResponse
    {
        // Nothing to resume. A school that finished after the email went out
        // should land somewhere that makes sense rather than back at step one
        // of a process it has already completed.
        if ($school->hasCompletedRegistration()) {
            return redirect()->route('dashboard');
        }

        $destination = route('subscriptions.choose-plan', absolute: false);

        $user = auth()->user();

        if ($user && $user->school_id === $school->id) {
            return redirect()->to($destination);
        }

        // Signed in as somebody else. Not logged out - a forwarded link must
        // not be able to end a colleague's session - just sent back to their
        // own dashboard, where the link means nothing to them.
        if ($user) {
            return redirect()->route('dashboard');
        }

        // Signed out: the normal sign-in page.
        //
        // guest() keeps THIS url as where to return to, so signing in comes
        // straight back here and lands them on the wizard - the link is
        // followed again rather than remembered as a destination. That also
        // means the signature is re-checked after signing in, not waved
        // through on the strength of having been valid a moment ago.
        return redirect()->guest(route('login', absolute: false));
    }
}
