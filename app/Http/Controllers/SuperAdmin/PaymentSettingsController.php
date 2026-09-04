<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\PaymentMethodSetting;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

/**
 * Where the ScholarNest Team says how schools may pay, and what to pay into.
 *
 * These values used to be typed into a Blade template. Changing the account a
 * school transfers to meant editing a view and deploying, and until somebody
 * did, every school on the subscription page was being asked to pay into
 * whatever the template said. That is not a setting, it is a hard-coded fact
 * about a bank account.
 */
class PaymentSettingsController extends Controller
{
    public function index(): View
    {
        return view('super-admin.payment-settings.index', [
            'methods' => PaymentMethodSetting::query()->inDisplayOrder()->get(),
        ]);
    }

    /**
     * Turn a method on or off.
     *
     * The switch the brief asks for. Disabling one takes it off the school's
     * payment page AND makes PaymentMethodRequest refuse it, so a school
     * cannot submit through it by re-posting the form.
     */
    public function toggle(PaymentMethodSetting $method): RedirectResponse
    {
        $method->update(['is_enabled' => ! $method->is_enabled]);

        AuditLog::record(
            'payment-method.'.($method->is_enabled ? 'enabled' : 'disabled'),
            "{$method->label} was ".($method->is_enabled ? 'enabled' : 'disabled').' as a payment method.',
            $method,
        );

        return back()->with(
            'status',
            "{$method->label} is now ".($method->is_enabled ? 'available to schools.' : 'unavailable to schools.'),
        );
    }

    /**
     * Edit what a school is shown for one method.
     */
    public function update(Request $request, PaymentMethodSetting $method): RedirectResponse
    {
        $validated = $request->validate([
            'label' => ['required', 'string', 'max:80'],
            'description' => ['nullable', 'string', 'max:150'],
            'instructions' => ['nullable', 'string', 'max:1000'],

            'bank_name' => ['nullable', 'string', 'max:100'],
            'account_name' => ['nullable', 'string', 'max:120'],

            // Digits and spaces only. An account number is the one field on
            // this page where a typo costs somebody their money, so anything
            // that is not a number is refused rather than saved and shown.
            'account_number' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 ]+$/'],
            'sort_code' => ['nullable', 'string', 'max:30', 'regex:/^[0-9 \-]+$/'],
        ], [
            'account_number.regex' => 'An account number can only contain digits.',
            'sort_code.regex' => 'A sort code can only contain digits and dashes.',
        ]);

        $method->update([
            'label' => $validated['label'],
            'description' => $validated['description'] ?? null,
            'instructions' => $validated['instructions'] ?? null,
            'details' => [
                ...$method->details ?? [],
                'bank_name' => $validated['bank_name'] ?? null,
                'account_name' => $validated['account_name'] ?? null,
                'account_number' => isset($validated['account_number'])
                    ? preg_replace('/\s+/', '', $validated['account_number'])
                    : null,
                'sort_code' => $validated['sort_code'] ?? null,
            ],
        ]);

        AuditLog::record(
            'payment-method.updated',
            "Payment details for {$method->label} were updated.",
            $method,
        );

        return back()->with('status', "{$method->label} was updated. Schools see the new details immediately.");
    }
}
