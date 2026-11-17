<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Services\IdentifierGenerator;
use App\Services\IdentifierRegenerator;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

/**
 * Settings → Identification Numbers → Regenerate.
 *
 * Offered after a school sets or changes its School Code while students or
 * staff are already registered, and from the settings page whenever some
 * numbers still use an old code. Nothing changes until the admin confirms on
 * this page, which says how many students and staff will be affected.
 */
class IdentifierRegenerationController extends Controller
{
    public function __construct(private readonly IdentifierRegenerator $regenerator) {}

    public function show(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.settings.regenerate-identifiers', [
            'school' => $school,
            'students' => $this->regenerator->summary($school, IdentifierGenerator::ADMISSION),
            'staff' => $this->regenerator->summary($school, IdentifierGenerator::STAFF),
            'sampleAdmission' => sprintf('%s-%s-PRY-001', $school->school_code, $school->current_session ?: 'NOSESSION'),
            'sampleStaff' => sprintf('%s-STAFF-001', $school->school_code),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'type' => ['required', Rule::in([IdentifierGenerator::ADMISSION, IdentifierGenerator::STAFF])],
            'include_manual' => ['nullable', 'boolean'],
        ]);

        if (! $school->school_code) {
            return redirect()->route('settings.index')->withErrors(['school_code' => 'Set a school code before regenerating numbers.']);
        }

        $students = $validated['type'] === IdentifierGenerator::ADMISSION;

        $result = $this->regenerator->regenerate($school, $validated['type'], $request->boolean('include_manual'), $request->user());

        $message = $result['changed'] === 0
            ? ($students ? 'Every admission number already follows school code '.$school->school_code.'. Nothing was changed.' : 'Every Staff ID already follows school code '.$school->school_code.'. Nothing was changed.')
            : sprintf(
                '%d %s regenerated using school code %s. The previous %s kept on record and still work for signing in.',
                $result['changed'],
                $students ? ($result['changed'] === 1 ? 'admission number was' : 'admission numbers were') : ($result['changed'] === 1 ? 'Staff ID was' : 'Staff IDs were'),
                $school->school_code,
                $result['changed'] === 1 ? 'number is' : 'numbers are',
            );

        return redirect()->route('settings.identifiers')->with('status', $message);
    }
}
