<?php

namespace App\Http\Controllers\SuperAdmin;

use App\Http\Controllers\Controller;
use App\Models\AuditLog;
use App\Models\Setting;
use App\Support\ThemePreset;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class ThemeController extends Controller
{
    public function edit(): View
    {
        return view('super-admin.themes.edit', [
            'settings' => Setting::current(),
            'presets' => ThemePreset::PRESETS,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $validated = $request->validate([
            'theme_preset' => ['required', 'string', 'in:'.implode(',', array_keys(ThemePreset::PRESETS))],
        ]);

        $settings = Setting::current();
        $settings->update($validated);

        AuditLog::record('theme.updated', 'Changed platform color theme to '.ThemePreset::label($validated['theme_preset']).'.', $settings);

        return back()->with('status', 'Theme updated.');
    }

    public function updateLogo(Request $request): RedirectResponse
    {
        $request->validate([
            'logo' => ['required', 'image', 'mimes:png,jpg,jpeg,svg', 'max:2048'],
        ]);

        $settings = Setting::current();

        if ($settings->logo_path) {
            Storage::disk('public')->delete($settings->logo_path);
        }

        $file = $request->file('logo');
        $path = $file->storeAs('branding', 'logo-'.Str::random(8).'.'.$file->getClientOriginalExtension(), 'public');

        $settings->update(['logo_path' => $path]);

        AuditLog::record('theme.logo_updated', 'Updated platform logo.', $settings);

        return back()->with('status', 'Logo updated.');
    }

    public function updateFavicon(Request $request): RedirectResponse
    {
        $request->validate([
            'favicon' => ['required', 'image', 'mimes:png,ico', 'max:512'],
        ]);

        $settings = Setting::current();

        if ($settings->favicon_path) {
            Storage::disk('public')->delete($settings->favicon_path);
        }

        $file = $request->file('favicon');
        $path = $file->storeAs('branding', 'favicon-'.Str::random(8).'.'.$file->getClientOriginalExtension(), 'public');

        $settings->update(['favicon_path' => $path]);

        AuditLog::record('theme.favicon_updated', 'Updated platform favicon.', $settings);

        return back()->with('status', 'Favicon updated.');
    }
}
