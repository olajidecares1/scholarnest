<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Http\Controllers\Controller;
use App\Models\SchoolGalleryImage;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function edit(Request $request): View
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], ['hero_title' => $school->name]);

        return view('school-admin.website.edit', [
            'school' => $school,
            'website' => $website,
            'galleryImages' => $school->galleryImages,
        ]);
    }

    public function update(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], ['hero_title' => $school->name]);

        $validated = $request->validate([
            'hero_title' => ['required', 'string', 'max:150'],
            'hero_subtitle' => ['nullable', 'string', 'max:255'],
            'about_text' => ['nullable', 'string', 'max:5000'],
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $heroImagePath = $this->storeImage($request, 'hero_image', 'website');

        $website->update([
            ...collect($validated)->except('hero_image')->all(),
            'hero_image_path' => $heroImagePath ?: $website->hero_image_path,
        ]);

        return back()->with('status', 'Your website settings were updated.');
    }

    public function togglePublish(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], ['hero_title' => $school->name]);

        $website->update(['is_published' => ! $website->is_published]);

        return back()->with('status', $website->is_published ? 'Your website is now live.' : 'Your website was unpublished.');
    }

    public function storeGalleryImage(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'image' => ['required', 'image', 'max:5120'],
            'caption' => ['nullable', 'string', 'max:150'],
        ]);

        $path = $this->storeImage($request, 'image', 'gallery');

        $school->galleryImages()->create([
            'image_path' => $path,
            'caption' => $validated['caption'] ?? null,
            'sort_order' => $school->galleryImages()->max('sort_order') + 1,
        ]);

        return back()->with('status', 'Image added to the gallery.');
    }

    public function destroyGalleryImage(SchoolGalleryImage $image): RedirectResponse
    {
        abort_unless($image->school_id === auth()->user()->school_id, 403);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return back()->with('status', 'Image removed from the gallery.');
    }

    private function storeImage(Request $request, string $field, string $folder): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        $file = $request->file($field);
        $path = $file->storeAs($folder, (string) Str::uuid().'.'.$file->getClientOriginalExtension(), 'public');

        $this->optimizer->optimize(Storage::disk('public')->path($path), (string) $file->getMimeType());

        return $path;
    }
}
