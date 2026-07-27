<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Http\Requests\SuperAdmin\UpdateSettingsRequest;
use App\Models\AuditLog;
use App\Models\Setting;
use Illuminate\Http\RedirectResponse;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function edit(): View
    {
        return view('super-admin.settings.edit', [
            'settings' => Setting::current(),
        ]);
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
