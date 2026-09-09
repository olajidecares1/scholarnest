<?php

namespace App\Http\Controllers\Auth;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Log;
use Illuminate\Support\Facades\Password;
use Illuminate\View\View;

/**
 * "Forgot password?" — the request half.
 *
 * Only the `web` guard reaches this. Staff, Students and Parents deliberately
 * have no self-service reset and no route to one, so a request for those
 * accounts finds nothing here to answer it. See docs/PASSWORD-RESET-POLICY.md.
 *
 * THE ANSWER IS THE SAME WHETHER OR NOT THE ACCOUNT EXISTS. Laravel's default
 * returns "We can't find a user with that email address" when it does not,
 * which turns this form into a way to test whether somebody is a AkademicNest
 * administrator - useful to anyone preparing a phishing email, and free to run.
 * Both outcomes now produce the identical sentence.
 */
class PasswordResetLinkController extends Controller
{
    /**
     * What every request is told, whether or not it matched an account.
     */
    private const NEUTRAL_RESPONSE = 'If that email address has a AkademicNest account, a password reset link is on its way. Check your inbox, including your spam folder.';

    public function create(): View
    {
        return view('auth.forgot-password');
    }

    public function store(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'email' => ['required', 'email'],
        ]);

        $status = Password::sendResetLink($validated);

        $this->record($validated['email'], $status, $request->ip());

        // Deliberately not branching on $status. RESET_LINK_SENT, INVALID_USER
        // and RESET_THROTTLED all come back as the same sentence - the last of
        // those included, because "you asked too recently" also confirms the
        // address belongs to somebody.
        return back()->with('status', self::NEUTRAL_RESPONSE);
    }

    /**
     * Every request is recorded, including the ones that matched nothing.
     *
     * A run of requests against addresses that do not exist is what an account
     * enumeration attempt looks like, and it is only visible if the misses are
     * written down as well as the hits.
     *
     * The application log takes all of them; the audit log takes only the ones
     * with a real account behind them, because an audit entry is about
     * something that happened to an account and there is no account here.
     */
    private function record(string $email, string $status, ?string $ip): void
    {
        Log::info('Password reset requested', [
            'email' => $email,
            'ip' => $ip,
            'outcome' => $status,
        ]);

        if ($status !== Password::RESET_LINK_SENT) {
            return;
        }

        AuditLog::record(
            'password-reset.requested',
            "A password reset link was sent to {$email}.",
            actorName: 'System',
        );
    }
}
