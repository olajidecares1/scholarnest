<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Controllers\SchoolAdmin\WhatsAppBusinessController;
use App\Models\AuditLog;
use App\Models\PlatformWhatsAppSetting;
use App\Services\WhatsApp\MetaNumberCheck;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\PlatformWhatsApp;
use App\Support\WhatsAppNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Super Admin > WhatsApp: AkademicNest's own WhatsApp Business number, from
 * which result details go to every school's parents automatically, all at
 * once, and verification codes go to schools.
 *
 * Set here once, checked with Meta before it is saved, and used straight
 * away (PlatformWhatsApp), with no deploy and no server settings to edit.
 * The WHATSAPP_* server settings still work, as the fallback.
 */
class WhatsAppSettingsController extends Controller
{
    public function show(): View
    {
        $settings = PlatformWhatsAppSetting::current();

        return view('super-admin.settings.whatsapp', [
            'settings' => $settings,
            'tokenMask' => PlatformWhatsAppSetting::mask($settings->access_token),
            'secretMask' => PlatformWhatsAppSetting::mask($settings->app_secret),
            'on' => app(WhatsAppGateway::class)->isConfigured(),
            'codesOn' => app(WhatsAppGateway::class)->canSendCodes(),
            'fromServer' => ! PlatformWhatsApp::savedInSuperAdmin() && app(WhatsAppGateway::class)->isConfigured(),
            'resultTemplateBody' => WhatsAppBusinessController::TEMPLATE_BODY,
            'webhookUrl' => url('/api/webhooks/whatsapp'),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $settings = PlatformWhatsAppSetting::current();

        $validated = $request->validate([
            'phone_number_id' => ['required', 'string', 'regex:/^\d{6,30}$/'],
            // Blank keeps what is saved: secrets are never shown back.
            'access_token' => [filled($settings->access_token) ? 'nullable' : 'required', 'string', 'min:20', 'max:2048'],
            'result_template' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'verify_template' => ['nullable', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
            'app_secret' => ['nullable', 'string', 'max:255'],
        ], [
            'phone_number_id.regex' => 'The Phone number ID is the long number Meta shows under the WhatsApp number, digits only.',
            'access_token.required' => 'Paste the permanent access token from Meta.',
            'result_template.regex' => 'Template names are small letters, numbers and underscores, exactly as in Meta.',
            'verify_template.regex' => 'Template names are small letters, numbers and underscores, exactly as in Meta.',
            'template_language.regex' => 'The template language code, e.g. en or en_US, exactly as in Meta.',
        ]);

        $token = filled($validated['access_token'] ?? null) ? trim($validated['access_token']) : (string) $settings->access_token;

        // Wrong details are found now, not on the day results go out.
        $number = MetaNumberCheck::check($validated['phone_number_id'], $token);

        $settings->update([
            'phone_number_id' => $validated['phone_number_id'],
            'access_token' => $token,
            'result_template' => $validated['result_template'],
            'verify_template' => $validated['verify_template'] ?? null,
            'template_language' => $validated['template_language'],
            'app_secret' => filled($validated['app_secret'] ?? null) ? trim($validated['app_secret']) : $settings->app_secret,
            'webhook_verify_token' => $settings->webhook_verify_token ?: Str::random(40),
            'display_number' => $number['display_phone_number'] ?? null,
            'verified_name' => $number['verified_name'] ?? null,
            'checked_at' => now(),
        ]);

        PlatformWhatsApp::forget();
        AuditLog::record('settings.whatsapp', 'Saved AkademicNest\'s WhatsApp number ('.($number['display_phone_number'] ?? $validated['phone_number_id']).'): automatic WhatsApp sending is on for every school.');

        return redirect()->route('super-admin.settings.whatsapp')->with('status', 'Saved and checked with Meta. Automatic WhatsApp sending is now on for every school.');
    }

    /**
     * The result template, with sample values, to a phone: proves the whole
     * chain (number, token, approved template) end to end.
     */
    public function test(Request $request): RedirectResponse
    {
        $validated = $request->validate(['test_phone' => ['required', 'string', 'max:30']]);
        $digits = WhatsAppNumber::digits($validated['test_phone']);

        if ($digits === null) {
            throw ValidationException::withMessages(['test_phone' => 'Enter the whole phone number, e.g. 08031234567.']);
        }

        $gateway = app(WhatsAppGateway::class);

        if (! $gateway->isConfigured()) {
            throw ValidationException::withMessages(['test_phone' => 'Save AkademicNest\'s WhatsApp details first.']);
        }

        $values = ['Parent', 'Sample School', 'First Term 2026/2027', 'Sample Pupil', 'JSS 1', 'SAMPLE-001', 'TEST-TOKEN', url('/')];
        $delivery = $gateway->send($digits, 'AkademicNest test message.', $values, $gateway->resultTemplate());

        if (! $delivery->sent) {
            throw ValidationException::withMessages(['test_phone' => 'WhatsApp did not send it: '.$delivery->error]);
        }

        return back()->with('status', 'Test message sent to '.WhatsAppNumber::display($digits).'. Check that phone.');
    }

    public function destroy(): RedirectResponse
    {
        PlatformWhatsAppSetting::query()->delete();
        PlatformWhatsApp::forget();
        AuditLog::record('settings.whatsapp', 'Removed AkademicNest\'s WhatsApp number from Super Admin.');

        return redirect()->route('super-admin.settings.whatsapp')->with('status', 'Removed. Automatic WhatsApp sending is off unless the server settings provide a number.');
    }
}
