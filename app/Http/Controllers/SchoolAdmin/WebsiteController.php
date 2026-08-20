<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\PlanKey;
use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\NavLink;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Services\ImageOptimizer;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    private const PAGES = ['home', 'about', 'admissions', 'contact', 'footer'];

    public function __construct(private readonly ImageOptimizer $optimizer) {}

    public function edit(Request $request): View
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        return view('school-admin.website.edit', [
            'school' => $school,
            'website' => $website,
            'galleryImages' => $school->galleryImages,
            'heroSlides' => $school->heroSlides,
            'navLinks' => $school->navLinks,
            'upcomingEvents' => $school->events()->where('starts_at', '>=', now())->orderBy('starts_at')->take(5)->get(),
            'domains' => $school->customDomains()->orderByDesc('is_primary')->orderBy('domain')->get(),
            'hasCustomDomainAccess' => $school->hasPlanAccess(PlanKey::Exclusive),
        ]);
    }

    public function updateBlocks(Request $request, string $page): RedirectResponse
    {
        abort_unless(in_array($page, self::PAGES, true), 404);

        $school = $request->user()->school;

        $validated = $request->validate([
            'blocks' => ['required', 'json'],
        ]);

        $blocks = json_decode($validated['blocks'], true);

        abort_unless(is_array($blocks), 422, 'Invalid blocks payload.');

        $validator = Validator::make(['blocks' => $blocks], [
            'blocks' => ['array'],
            'blocks.*.section' => ['required', 'string', 'max:60'],
            'blocks.*.type' => ['required', 'string', Rule::in(['text', 'button', 'card'])],
            'blocks.*.content' => ['nullable', 'string', 'max:5000'],
            'blocks.*.secondary_content' => ['nullable', 'string', 'max:5000'],
            'blocks.*.url' => ['nullable', 'string', 'max:255'],
            'blocks.*.x' => ['required', 'numeric', 'between:0,100'],
            'blocks.*.y' => ['required', 'numeric', 'between:0,100'],
            'blocks.*.w' => ['required', 'numeric', 'between:0,100'],
            'blocks.*.h' => ['required', 'numeric', 'between:0,100'],
            'blocks.*.style' => ['required', 'array'],
        ]);

        $validated = $validator->validate();

        DB::transaction(function () use ($school, $page, $validated) {
            $school->websiteBlocks()->forPage($page)->delete();

            foreach ($validated['blocks'] as $index => $block) {
                $school->websiteBlocks()->create([
                    'page' => $page,
                    'section' => $block['section'],
                    'type' => $block['type'],
                    'content' => $block['content'] ?? null,
                    'secondary_content' => $block['secondary_content'] ?? null,
                    'url' => $block['url'] ?? null,
                    'x' => $block['x'],
                    'y' => $block['y'],
                    'w' => $block['w'],
                    'h' => $block['h'],
                    'style' => $block['style'],
                    'sort_order' => $index,
                ]);
            }
        });

        return back()->with('status', 'Your changes were saved and published.');
    }

    public function updateBrandColor(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        $validated = $request->validate([
            'brand_primary_color' => ['nullable', 'regex:/^#[0-9A-Fa-f]{6}$/'],
        ]);

        $website->update($validated);

        return back()->with('status', 'Your theme color was updated.');
    }

    public function updateHeaderHeroFields(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        $validated = $request->validate([
            'topbar_announcement' => ['nullable', 'string', 'max:150'],
            'topbar_badge_text' => ['nullable', 'string', 'max:100'],
            'topbar_link_text' => ['nullable', 'string', 'max:60'],
            'topbar_link_url' => ['nullable', 'string', 'max:255'],
            'whats_happening_title' => ['nullable', 'string', 'max:100'],
            'show_whats_happening' => ['boolean'],
            'hero_image' => ['nullable', 'image', 'max:5120'],
        ]);

        $validated['show_whats_happening'] = $request->boolean('show_whats_happening', true);

        $heroImagePath = $this->storeImage($request, 'hero_image', 'website');

        $website->update([
            ...collect($validated)->except(['hero_image'])->all(),
            'hero_image_path' => $heroImagePath ?: $website->hero_image_path,
        ]);

        return back()->with('status', 'Your Header settings were updated.');
    }

    public function updateContactFields(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        $validated = $request->validate([
            'contact_email' => ['nullable', 'email', 'max:255'],
            'contact_phone' => ['nullable', 'string', 'max:30'],
            'contact_address' => ['nullable', 'string', 'max:255'],
            'facebook_url' => ['nullable', 'url', 'max:255'],
            'twitter_url' => ['nullable', 'url', 'max:255'],
            'instagram_url' => ['nullable', 'url', 'max:255'],
        ]);

        $website->update($validated);

        return back()->with('status', 'Your contact settings were updated.');
    }

    public function togglePublish(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

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

    public function storeHeroSlide(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $request->validate([
            'image' => ['required', 'image', 'max:8192'],
        ]);

        $path = $this->storeImage($request, 'image', 'hero-slides');

        $school->heroSlides()->create([
            'image_path' => $path,
            'sort_order' => $school->heroSlides()->max('sort_order') + 1,
        ]);

        return back()->with('status', 'Slide added to the hero slider.');
    }

    public function destroyHeroSlide(HeroSlide $slide): RedirectResponse
    {
        abort_unless($slide->school_id === auth()->user()->school_id, 403);

        Storage::disk('public')->delete($slide->image_path);
        $slide->delete();

        return back()->with('status', 'Slide removed from the hero slider.');
    }

    public function moveHeroSlide(Request $request, HeroSlide $slide): RedirectResponse
    {
        abort_unless($slide->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $siblings = HeroSlide::where('school_id', $slide->school_id)->orderBy('sort_order')->get();
        $index = $siblings->search(fn (HeroSlide $s) => $s->id === $slide->id);
        $swapIndex = $validated['direction'] === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $siblings->count()) {
            return back();
        }

        $neighbor = $siblings[$swapIndex];
        [$slideOrder, $neighborOrder] = [$slide->sort_order, $neighbor->sort_order];

        $slide->update(['sort_order' => $neighborOrder]);
        $neighbor->update(['sort_order' => $slideOrder]);

        return back()->with('status', 'Slide order updated.');
    }

    public function storeNavLink(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'url' => ['required', 'string', 'max:255'],
        ]);

        $school->navLinks()->create([
            ...$validated,
            'sort_order' => $school->navLinks()->max('sort_order') + 1,
        ]);

        return back()->with('status', 'Nav link added.');
    }

    public function updateNavLink(Request $request, NavLink $navLink): RedirectResponse
    {
        abort_unless($navLink->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'url' => ['required', 'string', 'max:255'],
        ]);

        $navLink->update($validated);

        return back()->with('status', 'Nav link updated.');
    }

    public function destroyNavLink(NavLink $navLink): RedirectResponse
    {
        abort_unless($navLink->school_id === auth()->user()->school_id, 403);

        $navLink->delete();

        return back()->with('status', 'Nav link removed.');
    }

    public function moveNavLink(Request $request, NavLink $navLink): RedirectResponse
    {
        abort_unless($navLink->school_id === auth()->user()->school_id, 403);

        $validated = $request->validate([
            'direction' => ['required', Rule::in(['up', 'down'])],
        ]);

        $siblings = NavLink::where('school_id', $navLink->school_id)->orderBy('sort_order')->get();
        $index = $siblings->search(fn (NavLink $link) => $link->id === $navLink->id);
        $swapIndex = $validated['direction'] === 'up' ? $index - 1 : $index + 1;

        if ($swapIndex < 0 || $swapIndex >= $siblings->count()) {
            return back();
        }

        $neighbor = $siblings[$swapIndex];
        [$linkOrder, $neighborOrder] = [$navLink->sort_order, $neighbor->sort_order];

        $navLink->update(['sort_order' => $neighborOrder]);
        $neighbor->update(['sort_order' => $linkOrder]);

        return back()->with('status', 'Nav link order updated.');
    }

    /**
     * @return array<string, mixed>
     */
    private function defaultAttributes(School $school): array
    {
        return [
            'hero_title' => $school->name,
            'stats' => [
                ['label' => 'Students', 'value' => $school->students()->count().'+'],
                ['label' => 'Qualified Staff', 'value' => (string) $school->staff()->count()],
                ['label' => 'Classes Offered', 'value' => (string) $school->schoolClasses()->count()],
                ['label' => 'Academic Levels', 'value' => (string) $school->academicLevels()->count()],
            ],
        ];
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
