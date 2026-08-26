<?php

namespace App\Http\Controllers\Guardian;

use App\Http\Controllers\Concerns\NotifiesSchoolOfProfileChanges;
use App\Http\Controllers\Controller;
use App\Models\School;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rules\Password;
use Illuminate\View\View;

class SettingsController extends Controller
{
    use NotifiesSchoolOfProfileChanges;

    public function index(Request $request, School $school): View
    {
        return view('guardian.settings.index', [
            'school' => $school,
            'guardian' => $request->user('guardian'),
        ]);
    }

    /**
     * A parent's own contact details.
     *
     * Their Parent ID, their password, and which children are linked to them
     * are all absent by design. The last of those matters most: a parent who
     * could attach a child to their own account could read that child's
     * results, so linking stays entirely with the School Admin.
     */
    public function updateProfile(Request $request, School $school): RedirectResponse
    {
        $guardian = $request->user('guardian');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:150'],
            'phone' => ['nullable', 'string', 'max:30'],
            // Required, not optional: guardians.email is NOT NULL, and it is
            // how the school reaches a parent when a phone number fails.
            'email' => ['required', 'email', 'max:255'],
        ]);

        $this->applyProfileChanges(
            $guardian,
            [
                'name' => $validated['name'],
                'phone' => $validated['phone'] ?? null,
                'email' => $validated['email'],
            ],
            $validated['name'],
            'parent/guardian',
        );

        return back()->with('status', 'Your details were updated. Your school has been notified.');
    }
}
