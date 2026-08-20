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

        $wantsAutoGeneration = $request->boolean('auto_generate_admission_numbers') || $request->boolean('auto_generate_staff_ids');

        $validated = $request->validate([
            'name' => ['required', 'string', 'max:255'],
            'timezone' => ['required', Rule::in(DateTimeZone::listIdentifiers())],
            'current_session' => ['nullable', 'string', 'max:20'],
            'logo' => ['nullable', 'image', 'max:5120'],
            'favicon' => ['nullable', 'image', 'max:1024'],
            'school_code' => [
                $wantsAutoGeneration ? 'required' : 'nullable',
                'string',
                'max:20',
                Rule::unique('schools', 'school_code')->ignore($school->id),
            ],
        ], [
            'school_code.required' => 'Set a school code before turning on automatic ID generation.',
            'school_code.unique' => 'This school code is already in use by another school. Please choose a different one.',
        ]);

        $validated['automatic_grading'] = $request->boolean('automatic_grading');
        $validated['auto_generate_admission_numbers'] = $request->boolean('auto_generate_admission_numbers');
        $validated['auto_generate_staff_ids'] = $request->boolean('auto_generate_staff_ids');

        $logoPath = null;
        if ($request->hasFile('logo')) {
            $file = $request->file('logo');
            $logoPath = $file->storeAs('school-logos', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');
            $this->optimizer->optimize(Storage::disk('public')->path($logoPath), (string) $file->getMimeType());
        }

        $faviconPath = null;
        if ($request->hasFile('favicon')) {
            $file = $request->file('favicon');
            $faviconPath = $file->storeAs('school-favicons', (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');
            $this->optimizer->optimize(Storage::disk('public')->path($faviconPath), (string) $file->getMimeType());
        }

        $school->update([
            ...collect($validated)->except(['logo', 'favicon'])->all(),
            'logo_path' => $logoPath ?: $school->logo_path,
            'favicon_path' => $faviconPath ?: $school->favicon_path,
        ]);

        return back()->with('status', 'Your school settings were updated.');
    }
}
