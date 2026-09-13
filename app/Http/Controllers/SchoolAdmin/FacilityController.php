<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\SchoolFacility;
use App\Rules\UploadedImage;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\View\View;

class FacilityController extends Controller
{
    use AuthorizesSchoolOwnership;

    public function __construct(private readonly UploadStorage $uploads) {}

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
            'image' => UploadedImage::rules(ImageProfile::Website),
        ];
    }

    private function storeImage(Request $request): ?string
    {
        if (! $request->hasFile('image')) {
            return null;
        }

        return $this->uploads->storeImage($request->file('image'), 'public', 'facilities', ImageProfile::Website, 'image')->path;
    }

    private function authorizeFacility(SchoolFacility $facility): void
    {
        $this->authorizeSchoolOwnership($facility);
    }
}
