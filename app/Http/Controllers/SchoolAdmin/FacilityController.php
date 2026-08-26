<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolFacility;
use App\Services\ImageOptimizer;
use App\Support\StoredUpload;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FacilityController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function index(Request $request): View
    {
        $school = $request->user()->school;

        return view('school-admin.facilities.index', [
            'facilities' => $school->facilities,
        ]);
    }

    public function store(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $validated = $request->validate($this->rules());

        $facility = $school->facilities()->create([
            ...collect($validated)->except('image')->all(),
            'image_path' => $this->storeImage($request),
            'sort_order' => $school->facilities()->max('sort_order') + 1,
        ]);

        return back()->with('status', "\"{$facility->name}\" was added.");
    }

    public function update(Request $request, SchoolFacility $facility): RedirectResponse
    {
        $this->authorizeFacility($facility);

        $validated = $request->validate($this->rules());
        $imagePath = $this->storeImage($request);

        $facility->update([
            ...collect($validated)->except('image')->all(),
            'image_path' => $imagePath ?: $facility->image_path,
        ]);

        return back()->with('status', "\"{$facility->name}\" was updated.");
    }

    public function destroy(SchoolFacility $facility): RedirectResponse
    {
        $this->authorizeFacility($facility);

        if ($facility->image_path) {
            Storage::disk('public')->delete($facility->image_path);
        }

        $name = $facility->name;
        $facility->delete();

        return back()->with('status', "\"{$name}\" was removed.");
    }

    /**
     * @return array<string, mixed>
     */
    private function rules(): array
    {
        return [
            'name' => ['required', 'string', 'max:150'],
            'category' => ['nullable', 'string', 'max:100'],
            'description' => ['nullable', 'string', 'max:1000'],
            'image' => ['nullable', 'image', 'max:5120'],
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        $file = $request->file('image');
        $path = $file->storeAs('facilities', StoredUpload::name($file), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }

    private function authorizeFacility(SchoolFacility $facility): void
    {
        abort_unless($facility->school_id === auth()->user()->school_id, 403);
    }
}
