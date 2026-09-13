<?php

namespace App\Http\Controllers\SchoolAdmin;

use App\Enums\PlanKey;
use App\Http\Controllers\Concerns\AuthorizesSchoolOwnership;
use App\Http\Controllers\Controller;
use App\Models\HeroSlide;
use App\Models\NavLink;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Rules\UploadedImage;
use App\Services\Uploads\UploadStorage;
use App\Support\Uploads\ImageProfile;
use App\Support\WebsiteTypography;
use Illuminate\Http\RedirectResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Facades\Validator;
use Illuminate\Validation\Rule;
use Illuminate\View\View;

class WebsiteController extends Controller
{
    use AuthorizesSchoolOwnership;

    private const PAGES = ['home', 'about', 'admissions', 'contact', 'footer'];

    public function __construct(private readonly UploadStorage $uploads) {}

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

    /**
     * The typeface and base weight the school's website is set in.
     *
     * The family is validated against the curated list rather than merely
     * being a string, because this value ends up inside a CSS declaration on
     * every visitor's page - an unchecked one would be a way to point the site
     * at an arbitrary font host.
     *
     * The weight is checked against what THAT family publishes, not against a
     * general range: asking Google for a weight a family does not ship returns
     * a stylesheet without it and leaves the browser to synthesise the
     * difference, which looks worse than the weight the school actually asked
     * for.
     */
    public function updateTypography(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        $validated = $request->validate([
            'font_family' => ['required', 'string', Rule::in(array_keys(WebsiteTypography::families()))],
            'font_weight' => ['required', 'integer'],
        ], [
            'font_family.in' => 'Please choose one of the fonts in the list.',
        ]);

        $available = WebsiteTypography::weightsFor($validated['font_family']);

        if (! in_array((int) $validated['font_weight'], $available, true)) {
            return back()->withErrors([
                'font_weight' => 'That weight is not available for '.$validated['font_family'].'. Available: '.implode(', ', $available).'.',
            ]);
        }

        $website->update($validated);

        return back()->with('status', 'Your website typography was updated.');
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
            'hero_image' => UploadedImage::rules(ImageProfile::Website),
        ]);

        $validated['show_whats_happening'] = $request->boolean('show_whats_happening', true);

        $heroImagePath = $this->storeImage($request, 'hero_image', 'website');

        $website->update([
            ...collect($validated)->except(['hero_image'])->all(),
            'hero_image_path' => $heroImagePath ?: $website->hero_image_path,
        ]);

        return back()->with('status', 'Your Header settings were updated.');
    }

    /**
     * The About section: what a school says about itself, and the three
     * statements a parent is actually weighing.
     */
    public function updateAboutFields(Request $request): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        $validated = $request->validate([
            'about_headline' => ['nullable', 'string', 'max:180'],
            'about_text' => ['nullable', 'string', 'max:2000'],
            'mission' => ['nullable', 'string', 'max:500'],
            'vision' => ['nullable', 'string', 'max:500'],
            'values' => ['nullable', 'string', 'max:500'],
            'about_image' => UploadedImage::rules(ImageProfile::Website),

            // The Principal's Desk and the Quote of the Week. The columns were
            // here all along - the section that showed them was removed and
            // the fields went with it, leaving data a school could not reach.
            'principal_name' => ['nullable', 'string', 'max:150'],
            'principal_title' => ['nullable', 'string', 'max:120'],
            'principal_message' => ['nullable', 'string', 'max:2000'],
            'principal_photo' => UploadedImage::rules(ImageProfile::Portrait),
            'quote_text' => ['nullable', 'string', 'max:500'],
            'quote_author' => ['nullable', 'string', 'max:150'],
            'quote_author_role' => ['nullable', 'string', 'max:120'],
        ]);

        $aboutImagePath = $this->storeImage($request, 'about_image', 'website');
        $principalPhotoPath = $this->storeImage($request, 'principal_photo', 'website', ImageProfile::Portrait);

        $website->update([
            ...collect($validated)->except(['about_image', 'principal_photo'])->all(),

            // Left alone when no new file is chosen, so editing the wording
            // does not clear the photograph.
            'about_image_path' => $aboutImagePath ?: $website->about_image_path,
            'principal_photo_path' => $principalPhotoPath ?: $website->principal_photo_path,
        ]);

        return back()->with('status', 'Your About section was updated.');
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
            'image' => UploadedImage::rules(ImageProfile::Website, required: true),
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
        $this->authorizeSchoolOwnership($image);

        Storage::disk('public')->delete($image->image_path);
        $image->delete();

        return back()->with('status', 'Image removed from the gallery.');
    }

    public function storeHeroSlide(Request $request): RedirectResponse
    {
        $school = $request->user()->school;

        $request->validate([
            'image' => UploadedImage::rules(ImageProfile::Website, required: true),
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
        $this->authorizeSchoolOwnership($slide);

        Storage::disk('public')->delete($slide->image_path);
        $slide->delete();

        return back()->with('status', 'Slide removed from the hero slider.');
    }

    public function moveHeroSlide(Request $request, HeroSlide $slide): RedirectResponse
    {
        $this->authorizeSchoolOwnership($slide);

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
        $this->authorizeSchoolOwnership($navLink);

        $validated = $request->validate([
            'label' => ['required', 'string', 'max:40'],
            'url' => ['required', 'string', 'max:255'],
        ]);

        $navLink->update($validated);

        return back()->with('status', 'Nav link updated.');
    }

    public function destroyNavLink(NavLink $navLink): RedirectResponse
    {
        $this->authorizeSchoolOwnership($navLink);

        $navLink->delete();

        return back()->with('status', 'Nav link removed.');
    }

    public function moveNavLink(Request $request, NavLink $navLink): RedirectResponse
    {
        $this->authorizeSchoolOwnership($navLink);

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

    /**
     * The background behind the Latest News AND Upcoming Events card.
     *
     * ONE setting for both. They are one card as far as a school is concerned,
     * and a control on each page would say they were two - so the Events page
     * points at this one rather than repeating it.
     */
    public function updateNewsEventsCardBackground(Request $request): RedirectResponse
    {
        return $this->updateCardBackground(
            $request,
            'news_events_card_image_path',
            'The background for your News & Events card was updated.',
            'The background image was removed. The card is back to its plain background.',
        );
    }

    /**
     * The background behind the Academic Excellence card, which is a separate
     * card and so has a separate setting.
     */
    public function updateAcademicsCardBackground(Request $request): RedirectResponse
    {
        return $this->updateCardBackground(
            $request,
            'academics_card_image_path',
            'The background for your Academic Excellence card was updated.',
            'The background image was removed. The card is back to its plain background.',
        );
    }

    /**
     * The background behind the About band.
     */
    public function updateAboutCardBackground(Request $request): RedirectResponse
    {
        return $this->updateCardBackground(
            $request,
            'about_card_image_path',
            'The background for your About section was updated.',
            'The background image was removed. The section is back to its plain background.',
        );
    }

    /**
     * Upload or remove one card background.
     *
     * The COLUMN is chosen by the two methods above, never by the request.
     * Taking a field name from the form would let anyone with a browser write
     * to any column on this row, and the two callers are the only things that
     * should decide which card they are setting.
     *
     * The school comes from the signed-in user, so an image can only ever be
     * attached to the uploader's own school - there is no school id in the
     * form to tamper with.
     */
    private function updateCardBackground(Request $request, string $column, string $savedMessage, string $removedMessage): RedirectResponse
    {
        $school = $request->user()->school;
        $website = $school->website()->firstOrCreate([], $this->defaultAttributes($school));

        $request->validate([
            // "image" checks the file is really an image rather than something
            // renamed to look like one; the format list keeps it to the ones a
            // browser will actually render as a background.
            // 1200 wide, not 600.
            //
            // These sit behind a full-width band, so the browser stretches
            // whatever it is given across the whole page. A 600px image on a
            // 1400px section is being blown up more than twice, and no amount
            // of care elsewhere makes an upscaled photograph look sharp - it
            // just looks soft, which is exactly how the first one did.
            'background_image' => [...UploadedImage::rules(ImageProfile::Website), 'dimensions:min_width=1200,min_height=500'],
            'remove' => ['nullable', 'boolean'],
        ], [
            'background_image.dimensions' => 'That image is too small to stay sharp across the full width of the page. Please choose one at least 1200 by 500 pixels — wider is better.',
        ]);

        if ($request->boolean('remove')) {
            $existing = $website->{$column};

            $website->update([$column => null]);

            if ($existing) {
                Storage::disk('public')->delete($existing);
            }

            return back()->with('status', $removedMessage);
        }

        $path = $this->storeImage($request, 'background_image', 'website/cards');

        if (! $path) {
            return back()->with('status', 'Choose an image first.');
        }

        $previous = $website->{$column};

        $website->update([$column => $path]);

        // Only once the new one is safely recorded, so a failed write cannot
        // leave the card pointing at a file that is no longer there.
        if ($previous && $previous !== $path) {
            Storage::disk('public')->delete($previous);
        }

        return back()->with('status', $savedMessage);
    }

    private function storeImage(Request $request, string $field, string $folder, ImageProfile $profile = ImageProfile::Website): ?string
    {
        if (! $request->hasFile($field)) {
            return null;
        }

        return $this->uploads->storeImage($request->file($field), 'public', $folder, $profile, $field)->path;
    }
}
