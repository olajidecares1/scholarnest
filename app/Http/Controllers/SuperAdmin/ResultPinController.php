<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Enums\ResultCheckingPinStatus;
use App\Enums\ResultTokenAccessOutcome;
use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\ResultCheckingPin;
use App\Models\ResultTokenAccessLog;
use App\Models\School;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Super Admin oversight of result tokens.
 *
 * Schools issue their own tokens now, so this is no longer a place where work
 * gets done on their behalf, it is where the platform can see what is being
 * issued and redeemed across every school, and step in when something looks
 * wrong.
 *
 * Deliberately covers all three plans. Tokens used to be a Basic-only feature
 * and this screen only listed Basic schools, which meant most of the platform's
 * result access was invisible from here.
 */
class ResultPinController extends Controller
{
    public function index(Request $request): View
    {
        $query = School::query()
            ->withCount([
                'resultCheckingPins',
                'resultCheckingPins as active_pins_count' => fn ($q) => $q->where('status', ResultCheckingPinStatus::Active),
                'resultCheckingPins as revoked_pins_count' => fn ($q) => $q->where('status', ResultCheckingPinStatus::Revoked),
            ]);

        if ($search = $request->query('search')) {
            $query->where('name', 'like', '%'.$search.'%');
        }

        return view('super-admin.result-pins.index', [
            'schools' => $query->orderBy('name')->paginate(15)->withQueryString(),

            // Platform-wide totals, so a spike in failures is visible without
            // opening each school in turn.
            'stats' => [
                'tokens' => ResultCheckingPin::count(),
                'active' => ResultCheckingPin::where('status', ResultCheckingPinStatus::Active)->count(),
                'revoked' => ResultCheckingPin::where('status', ResultCheckingPinStatus::Revoked)->count(),
                'suspended' => ResultCheckingPin::where('status', ResultCheckingPinStatus::Suspended)->count(),
                'views_today' => ResultTokenAccessLog::where('outcome', ResultTokenAccessOutcome::Succeeded)
                    ->whereDate('occurred_at', today())
                    ->count(),
                'failures_today' => ResultTokenAccessLog::failed()
                    ->whereDate('occurred_at', today())
                    ->count(),
            ],

            // The attempts worth a second look, newest first.
            'suspicious' => ResultTokenAccessLog::query()
                ->suspicious()
                ->with(['school', 'student'])
                ->latest('occurred_at')
                ->limit(20)
                ->get(),

            // The defaults new tokens inherit, editable on this page.
            'settings' => Setting::current(),
        ]);
    }

    public function show(Request $request, School $school): View
    {
        $tokens = $school->resultCheckingPins()
            ->with(['examination', 'boundStudent'])
            ->when($request->filled('status'), fn ($q) => $q->where('status', $request->string('status')->toString()))
            ->latest()
            ->paginate(20)
            ->withQueryString();

        return view('super-admin.result-pins.show', [
            'school' => $school,
            'tokens' => $tokens,
            'statuses' => ResultCheckingPinStatus::cases(),
            'accessLogs' => ResultTokenAccessLog::query()
                ->forSchool($school)
                ->with(['student', 'examination'])
                ->latest('occurred_at')
                ->limit(50)
                ->get(),
        ]);
    }

    /**
     * Revoke a token anywhere on the platform.
     *
     * The one action the Super Admin still takes on a school's behalf, because
     * shutting off access to a result that is being abused should not wait for
     * the school to notice.
     */
    public function revoke(ResultCheckingPin $pin): RedirectResponse
    {
        $pin->update(['status' => ResultCheckingPinStatus::Revoked]);

        AuditLog::record(
            'result-token.revoked-by-platform',
            "Revoked a result token belonging to {$pin->school->name}.",
            $pin,
        );

        return back()->with('status', 'That token was revoked and can no longer be used.');
    }

    /**
     * Change the defaults that newly issued tokens inherit.
     *
     * Applies at issue, not at redemption, so tightening these never breaks a
     * token a parent is already holding, it only shapes what schools mint
     * from here on.
     */
    public function updateSettings(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            // Capped rather than unbounded: a token good for a thousand views
            // is a token worth forwarding, which is the thing this system
            // exists to prevent.
            'result_token_max_uses' => ['required', 'integer', 'min:1', 'max:50'],

            // Blank means no expiry, which stays the default.
            'result_token_expiry_days' => ['nullable', 'integer', 'min:1', 'max:730'],
        ]);

        $settings = Setting::current();
        $settings->update($validated);

        AuditLog::record(
            'result-token.settings-updated',
            "Set the platform default to {$validated['result_token_max_uses']} view(s) per token, "
                .($validated['result_token_expiry_days'] ? "expiring after {$validated['result_token_expiry_days']} day(s)." : 'with no expiry.'),
            $settings,
        );

        return back()->with('status', 'Global token settings updated.');
    }
}
