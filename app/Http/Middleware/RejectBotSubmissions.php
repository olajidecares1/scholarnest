<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * A trap field for the forms strangers can reach.
 *
 * Registration, the public report form and result checking are open to anyone
 * with the URL, which means they are open to anything that crawls URLs. Rate
 * limiting already blunts a determined attacker; this is aimed at the far more
 * common nuisance, the scripted submitter that fills in every input it finds
 * and posts the lot.
 *
 * The field is present in the markup, hidden from people by CSS and marked
 * aria-hidden so a screen reader skips it too. A person never sees it and so
 * never fills it. Something reading the HTML sees an input and fills it in,
 * and that is the whole signal.
 *
 * Deliberately NOT a captcha. The brief asks that protection not be made
 * difficult for legitimate users, and every parent checking a result on a poor
 * connection would pay for a captcha - while an attacker with any real intent
 * would solve it for a fraction of a penny. A trap costs an honest user
 * nothing at all.
 *
 * Its limits, plainly: it stops naive automation, not somebody who has looked
 * at the page once. That is why it is one layer beside rate limiting rather
 * than a replacement for it.
 *
 * A missing field is treated as legitimate rather than rejected. Only a FILLED
 * one is refused - otherwise every non-browser client, and every form that
 * forgets to render it, breaks in a way nobody would connect to this.
 */
class RejectBotSubmissions
{
    /**
     * The trap's name. Unremarkable on purpose - "website" is the sort of
     * field a scripted submitter is delighted to complete.
     */
    public const FIELD = 'website_url';

    /**
     * Handle an incoming request.
     *
     * @param  Closure(Request): (Response)  $next
     */
    public function handle(Request $request, Closure $next): Response
    {
        if ($request->isMethod('GET') || blank($request->input(self::FIELD))) {
            return $next($request);
        }

        // 422, and a message that says nothing about why. Telling a script it
        // tripped a honeypot is telling it which field to leave alone next
        // time.
        if ($request->expectsJson()) {
            return response()->json(['message' => 'This request could not be processed.'], 422);
        }

        return back()
            ->withInput($request->except([self::FIELD, 'password', 'password_confirmation']))
            ->withErrors(['form' => 'This request could not be processed. Please try again.']);
    }
}
