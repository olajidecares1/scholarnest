<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolWhatsAppAccount;
use App\Services\WhatsApp\MetaNumberCheck;
use App\Services\WhatsApp\SchoolWhatsAppVerifier;
use App\Services\WhatsApp\WhatsAppGateway;
use App\Support\WhatsAppNumber;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\ValidationException;
use Illuminate\View\View;

/**
 * Settings > WhatsApp Business: the school connects its own registered
 * WhatsApp Business number (Meta's WhatsApp Cloud API), and result details
 * go to its parents from that number automatically.
 *
 * The details are checked with Meta before they are saved, so a mistyped
 * token is found here rather than on the day results are sent.
 */
class WhatsAppBusinessController extends Controller
{
    /**
     * The template the school creates in Meta, word for word, so its eight
     * blanks match the values sent (see ResultDetailsNotification::forWhatsApp).
     */
    public const TEMPLATE_BODY = "Dear {{1}}, {{2}} has released the {{3}} result for your child.\n\n"
        ."Name: {{4}}\nClass: {{5}}\nAdmission number: {{6}}\nResult token: {{7}}\nCheck the result: {{8}}\n\n"
        .'This token opens this one result only. Please keep it private.';

    public function show(Request $request, SchoolWhatsAppVerifier $verifier): View
    {
        $school = $request->user()->school;

        return view('school-admin.settings.whatsapp', [
            'school' => $school,
            'account' => SchoolWhatsAppAccount::query()->where('school_id', $school->id)->first(),
            'templateBody' => self::TEMPLATE_BODY,
            'verified' => $school->verifiedWhatsApp(),
            'pending' => $verifier->pending($school),
            'resendIn' => $verifier->secondsUntilResend($school),
            'canVerify' => $verifier->canVerify(),
        ]);
    }

    /**
     * Send a 6-digit code to the school's WhatsApp number, ordinary or
     * Business: it arriving proves the number is on WhatsApp.
     */
    public function sendNumberCode(Request $request, SchoolWhatsAppVerifier $verifier): RedirectResponse
    {
        $validated = $request->validate([
            'whatsapp_number' => ['required', 'string', 'max:60'],
        ], [
            'whatsapp_number.required' => "Enter your school's WhatsApp number.",
        ]);

        $school = $request->user()->school;
        $problem = $verifier->sendCode($school, $validated['whatsapp_number']);

        if ($problem !== null) {
            throw ValidationException::withMessages(['whatsapp_number' => $problem]);
        }

        $number = WhatsAppNumber::display((string) $verifier->pending($school)['number']);

        return redirect()->route('settings.whatsapp')
            ->with('status', "We sent a 6-digit code to {$number} on WhatsApp. Type it below.");
    }

    public function verifyNumber(Request $request, SchoolWhatsAppVerifier $verifier): RedirectResponse
    {
        $validated = $request->validate([
            'code' => ['required', 'string', 'regex:/^\s*\d{6}\s*$/'],
        ], [
            'code.required' => 'Type the 6-digit code from WhatsApp.',
            'code.regex' => 'The code is 6 digits.',
        ]);

        $school = $request->user()->school;
        $problem = $verifier->verify($school, $validated['code']);

        if ($problem !== null) {
            throw ValidationException::withMessages(['code' => $problem]);
        }

        return redirect()->route('settings.whatsapp')
            ->with('status', 'Verified. '.WhatsAppNumber::display((string) $school->fresh()->verifiedWhatsApp())." is your school's WhatsApp number, and parents will see it on every result message.");
    }

    public function cancelNumberCode(Request $request, SchoolWhatsAppVerifier $verifier): RedirectResponse
    {
        $verifier->cancel($request->user()->school);

        return redirect()->route('settings.whatsapp');
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $existing = SchoolWhatsAppAccount::query()->where('school_id', $school->id)->first();

        $validated = $request->validate([
            'phone_number_id' => ['required', 'string', 'regex:/^\d{6,30}$/'],
            // Blank keeps the saved token: it is never shown back in full.
            'access_token' => [$existing ? 'nullable' : 'required', 'string', 'min:20', 'max:2048'],
            'result_template' => ['required', 'string', 'max:512', 'regex:/^[a-z0-9_]+$/'],
            'template_language' => ['required', 'string', 'max:16', 'regex:/^[a-z]{2,3}(_[A-Z]{2})?$/'],
        ], [
            'phone_number_id.regex' => 'The Phone number ID is the long number Meta shows under your WhatsApp number, digits only.',
            'access_token.required' => 'Paste the access token from Meta.',
            'result_template.regex' => 'Template names are small letters, numbers and underscores, exactly as in Meta (e.g. result_details).',
            'template_language.regex' => 'The template language code, e.g. en or en_US, exactly as in Meta.',
        ]);

        $token = filled($validated['access_token'] ?? null) ? trim($validated['access_token']) : (string) $existing?->access_token;
        $phone = MetaNumberCheck::check($validated['phone_number_id'], $token);

        SchoolWhatsAppAccount::updateOrCreate(['school_id' => $school->id], [
            'phone_number_id' => $validated['phone_number_id'],
            'access_token' => $token,
            'result_template' => $validated['result_template'],
            'template_language' => $validated['template_language'],
            'display_number' => $phone['display_phone_number'] ?? null,
            'verified_name' => $phone['verified_name'] ?? null,
            'verified_at' => now(),
        ]);

        return redirect()->route('settings.whatsapp')->with('status', 'WhatsApp connected. Result details to your parents will now go out from '.($phone['display_phone_number'] ?? 'your WhatsApp number').' automatically.');
    }

    /**
     * A test message, the real template with sample values, to a number the
     * School Admin chooses (their own phone), so the whole chain is proven.
     */
    public function test(Request $request, WhatsAppGateway $whatsApp): RedirectResponse
    {
        $school = $request->user()->school;
        $school->unsetRelation('whatsAppAccount');
        abort_unless($school->whatsAppAccount, 404);

        $validated = $request->validate([
            'test_phone' => ['required', 'string', 'max:20'],
        ]);

        $digits = $this->international($validated['test_phone']);

        if ($digits === null) {
            throw ValidationException::withMessages(['test_phone' => 'Enter a phone number such as 08031234567 or +2348031234567.']);
        }

        $gateway = $whatsApp->forSchool($school);
        $values = ['Parent', (string) $school->name, 'First Term '.($school->current_session ?? ''), 'Sample Pupil', 'JSS 1', 'SAMPLE-001', 'TEST-TOKEN', (string) ($school->resultLinkUrl() ?? config('app.url'))];
        $text = 'This is a test message from '.$school->name.' on AkademicNest. WhatsApp is connected.';

        $delivery = $gateway->send($digits, $text, $values, $gateway->resultTemplate());

        return $delivery->sent
            ? back()->with('status', 'Test message sent to +'.$digits.'. Check that phone.')
            : back()->withErrors(['test_phone' => 'WhatsApp refused the test: '.$delivery->error]);
    }

    public function destroy(Request $request): RedirectResponse
    {
        SchoolWhatsAppAccount::query()->where('school_id', $request->user()->school_id)->delete();

        return redirect()->route('settings.whatsapp')->with('status', 'WhatsApp disconnected. Result details will be sent by email, with Send on WhatsApp buttons for WhatsApp.');
    }

    /**
     * 08031234567, +234 803 123 4567 or 2348031234567 as 2348031234567.
     */
    private function international(string $phone): ?string
    {
        $digits = (string) preg_replace('/\D/', '', $phone);

        if (str_starts_with($digits, '0') && strlen($digits) === 11) {
            $digits = '234'.substr($digits, 1);
        }

        return strlen($digits) >= 10 && strlen($digits) <= 15 ? $digits : null;
    }
}
