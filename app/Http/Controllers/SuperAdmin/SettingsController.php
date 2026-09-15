<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateSettingsRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Notifications\MailDeliveryTestNotification;
use App\Services\Mail\MailReadiness;
use App\Services\Mail\TransactionalMailer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(MailReadiness $readiness): View
    {
        return view('super-admin.settings.edit', [
            'settings' => Setting::current(),
            'mailSettings' => $readiness->summary(),
            'mailProblem' => $readiness->problem(),
        ]);
    }

    /**
     * Send a test email and say exactly what the mail server answered.
     *
     * The way to prove email works on this server, rather than assume it.
     */
    public function sendTestEmail(Request $request, TransactionalMailer $mailer): RedirectResponse
    {
        $validated = $request->validate([
            'test_email' => ['required', 'email', 'max:255'],
        ]);

        $delivery = $mailer->send($validated['test_email'], new MailDeliveryTestNotification('the Super Admin settings page'), 'test');

        AuditLog::record('settings.test-email', "Sent a test email to {$delivery->recipient}: {$delivery->status}.");

        return $delivery->wasSent()
            ? back()->with('mail_test_status', "Test email sent to {$delivery->recipient}. The mail server accepted it; check that inbox and its spam folder.")
            : back()->withInput()->with('mail_test_error', 'The test email was not sent. '.$delivery->error);
    }

    public function update(UpdateSettingsRequest $request): RedirectResponse
    {
        $validated = $request->validated();
        $validated['maintenance_mode'] = $request->boolean('maintenance_mode');

        $settings = Setting::current();
        $settings->update($validated);

        AuditLog::record('settings.updated', 'Updated system settings.', $settings);

        return back()->with('status', 'Settings updated successfully.');
    }
}
