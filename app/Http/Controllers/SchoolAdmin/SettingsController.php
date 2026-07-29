<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Services\ImageOptimizer;
use DateTimeZone;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class SettingsController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function edit(Request $request): View
    {
        return view('school-admin.settings.edit', [
            'school' => $request->user()->school,
            'timezones' => DateTimeZone::listIdentifiers(),
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'current_session' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:5120'],
        ]);

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $logoPath = $file->storeAs('school-logos', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');
            $this->optimizer->optimize(Storage::disk('public')->path($logoPath), (string) $file->getMimeType());
        }

        $school->update([
            ...collect($validated)->except('logo')->all(),
            'logo_path' => $logoPath ?: $school->logo_path,
        ]);

        return back()->with('status', 'Your school settings were updated.');
    }
}
