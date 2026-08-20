<?php

use App\Enums\CustomDomainSslStatus;
use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\CustomDomain;
use App\Models\HeroSlide;
use App\Models\NavLink;
use App\Models\Plan;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Models\Subscription;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = School::factory()->create(['name' => 'Bright Future Academy']);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

test('a school gets a unique slug automatically on creation', function () {
    expect($this->school->slug)->not->toBeNull();

    $other = School::factory()->create(['name' => 'Bright Future Academy']);
    expect($other->slug)->not->toBe($this->school->slug);
});

test('a school admin can view the website settings page with all sections', function () {
    $this->actingAs($this->admin)
        ->get(route('website.index'))
        ->assertStatus(200)
        ->assertSee('Publication Status')
        ->assertSee('Home Page')
        ->assertSee('About Us Page')
        ->assertSee('Admissions Page')
        ->assertSee('Contact Us Page')
        ->assertSee('Footer')
        ->assertSee('Gallery')
        ->assertSee('Navigation Menu');
});

test('a school admin can update the header settings independently', function () {
    $this->actingAs($this->admin)->put(route('website.update-header-hero-fields'), [
        'topbar_announcement' => 'Admissions are open.',
        'whats_happening_title' => 'Upcoming',
        'show_whats_happening' => '1',
    ])->assertRedirect();

    $website = $this->school->fresh()->website;
    expect($website->topbar_announcement)->toBe('Admissions are open.');
    expect($website->whats_happening_title)->toBe('Upcoming');
    expect($website->show_whats_happening)->toBeTrue();
});

test('a school admin can upload a hero image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->put(route('website.update-header-hero-fields'), [
        'hero_image' => UploadedFile::fake()->image('hero.jpg', 800, 400),
    ]);

    $website = $this->school->fresh()->website;
    expect($website->hero_image_path)->not->toBeNull();
    Storage::disk('public')->assertExists($website->hero_image_path);
});

test('a school admin can save a theme color by name, hex, or rgb', function () {
    $this->actingAs($this->admin)->put(route('website.update-brand-color'), [
        'brand_primary_color' => '#ff0000',
    ])->assertRedirect();

    expect($this->school->fresh()->website->brand_primary_color)->toBe('#ff0000');
});

test('theme color validation rejects non-hex values', function () {
    $this->actingAs($this->admin)->put(route('website.update-brand-color'), [
        'brand_primary_color' => 'blue',
    ])->assertSessionHasErrors('brand_primary_color');
});

test('a school admin can update contact details independently', function () {
    $this->actingAs($this->admin)->put(route('website.update-contact-fields'), [
        'contact_email' => 'info@brightfuture.test',
        'contact_phone' => '08012345678',
    ])->assertRedirect();

    $website = $this->school->fresh()->website;
    expect($website->contact_email)->toBe('info@brightfuture.test');
    expect($website->contact_phone)->toBe('08012345678');
});

test('updating one settings form does not clobber data saved under another', function () {
    $this->actingAs($this->admin)->put(route('website.update-header-hero-fields'), ['topbar_announcement' => 'Hello there.']);
    $this->actingAs($this->admin)->put(route('website.update-contact-fields'), ['contact_email' => 'info@brightfuture.test']);

    $website = $this->school->fresh()->website;
    expect($website->topbar_announcement)->toBe('Hello there.');
    expect($website->contact_email)->toBe('info@brightfuture.test');
});

test('a school admin can publish and unpublish the website', function () {
    $this->actingAs($this->admin)->post(route('website.publish'));
    expect($this->school->fresh()->website->is_published)->toBeTrue();

    $this->actingAs($this->admin)->post(route('website.publish'));
    expect($this->school->fresh()->website->is_published)->toBeFalse();
});

test('the public website page 404s when unpublished', function () {
    $this->get(route('public.school-website', $this->school))->assertNotFound();
});

test('the public website page shows content once published', function () {
    $this->actingAs($this->admin)->post(route('website.publish'));

    $this->get(route('public.school-website', $this->school))
        ->assertStatus(200)
        ->assertSee($this->school->fresh()->website->hero_title);
});

test('the public about us page 404s when unpublished', function () {
    $this->get(route('public.school-about.index', $this->school))->assertNotFound();
});

test('the public contact us page 404s when unpublished', function () {
    $this->get(route('public.school-contact.index', $this->school))->assertNotFound();
});

test('the public contact us page shows content once published', function () {
    $this->actingAs($this->admin)->put(route('website.update-contact-fields'), [
        'contact_email' => 'info@brightfuture.test',
        'contact_phone' => '08012345678',
    ]);
    $this->actingAs($this->admin)->post(route('website.publish'));

    $this->get(route('public.school-contact.index', $this->school))
        ->assertStatus(200)
        ->assertSee('info@brightfuture.test')
        ->assertSee('08012345678');
});

test('a school admin can add and remove a gallery image', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('website.gallery.store'), [
        'image' => UploadedFile::fake()->image('photo.jpg', 600, 400),
        'caption' => 'Sports Day',
    ]);

    $image = SchoolGalleryImage::where('school_id', $this->school->id)->firstOrFail();
    expect($image->caption)->toBe('Sports Day');
    Storage::disk('public')->assertExists($image->image_path);

    $this->actingAs($this->admin)->delete(route('website.gallery.destroy', $image));
    expect(SchoolGalleryImage::find($image->id))->toBeNull();
});

test('a school admin cannot remove another school\'s gallery image', function () {
    $otherSchool = School::factory()->create();
    $image = SchoolGalleryImage::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('website.gallery.destroy', $image))
        ->assertForbidden();
});

test('a school admin can add and remove a hero slide', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->post(route('website.hero-slides.store'), [
        'image' => UploadedFile::fake()->image('slide.jpg', 1600, 800),
    ]);

    $slide = HeroSlide::where('school_id', $this->school->id)->firstOrFail();
    Storage::disk('public')->assertExists($slide->image_path);

    $this->actingAs($this->admin)->delete(route('website.hero-slides.destroy', $slide));
    expect(HeroSlide::find($slide->id))->toBeNull();
});

test('a school admin can reorder hero slides', function () {
    $first = HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);
    $second = HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 1]);

    $this->actingAs($this->admin)->post(route('website.hero-slides.move', $second), ['direction' => 'up']);

    expect($first->fresh()->sort_order)->toBe(1);
    expect($second->fresh()->sort_order)->toBe(0);
});

test('a school admin cannot modify another school\'s hero slide', function () {
    $otherSchool = School::factory()->create();
    $slide = HeroSlide::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('website.hero-slides.destroy', $slide))
        ->assertForbidden();
});

test('a school admin can add, update, and remove a nav link', function () {
    $this->actingAs($this->admin)->post(route('website.nav-links.store'), [
        'label' => 'Alumni',
        'url' => '/alumni',
    ]);

    $link = NavLink::where('school_id', $this->school->id)->firstOrFail();
    expect($link->label)->toBe('Alumni');
    expect($link->url)->toBe('/alumni');

    $this->actingAs($this->admin)->put(route('website.nav-links.update', $link), [
        'label' => 'Alumni Network',
        'url' => '/alumni-network',
    ]);
    expect($link->fresh()->label)->toBe('Alumni Network');

    $this->actingAs($this->admin)->delete(route('website.nav-links.destroy', $link));
    expect(NavLink::find($link->id))->toBeNull();
});

test('a school admin can reorder nav links', function () {
    $first = NavLink::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);
    $second = NavLink::factory()->create(['school_id' => $this->school->id, 'sort_order' => 1]);

    $this->actingAs($this->admin)->post(route('website.nav-links.move', $second), ['direction' => 'up']);

    expect($first->fresh()->sort_order)->toBe(1);
    expect($second->fresh()->sort_order)->toBe(0);
});

test('a school admin cannot modify another school\'s nav link', function () {
    $otherSchool = School::factory()->create();
    $link = NavLink::factory()->create(['school_id' => $otherSchool->id]);

    $this->actingAs($this->admin)
        ->delete(route('website.nav-links.destroy', $link))
        ->assertForbidden();
});

test('the public homepage uses custom nav links when present, falling back to the default menu otherwise', function () {
    $this->actingAs($this->admin)->post(route('website.publish'));

    $this->get(route('public.school-website', $this->school))->assertDontSee('Alumni Portal');

    NavLink::factory()->create(['school_id' => $this->school->id, 'label' => 'Alumni Portal', 'url' => '/alumni', 'sort_order' => 0]);

    $this->get(route('public.school-website', $this->school))->assertSee('Alumni Portal');
});

test('the public homepage uses hero slides when present, falling back to the single hero image otherwise', function () {
    Storage::fake('public');

    $this->actingAs($this->admin)->put(route('website.update-header-hero-fields'), [
        'hero_image' => UploadedFile::fake()->image('fallback.jpg', 800, 400),
    ]);
    $this->actingAs($this->admin)->post(route('website.publish'));

    $fallbackUrl = $this->school->fresh()->website->heroImageUrl();
    $this->get(route('public.school-website', $this->school))->assertSee($fallbackUrl, false);

    HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);
    $slideUrl = $this->school->fresh()->heroSlides->first()->imageUrl();

    $this->get(route('public.school-website', $this->school))
        ->assertSee($slideUrl, false)
        ->assertDontSee($fallbackUrl, false);
});

test('the website settings page shows an upgrade prompt for the custom domain tab when the school is not on the exclusive plan', function () {
    $this->actingAs($this->admin)
        ->get(route('website.index'))
        ->assertStatus(200)
        ->assertSee('Connect Your Own Domain')
        ->assertSee('Upgrade to Exclusive');
});

test('the website settings page renders the custom domain setup wizard for an exclusive-plan school', function () {
    $plan = Plan::firstOrCreate(['key' => PlanKey::Exclusive], Plan::factory()->make(['key' => PlanKey::Exclusive])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    $domain = CustomDomain::factory()->create([
        'school_id' => $this->school->id,
        'domain' => 'www.brightfuture.com',
        'is_primary' => true,
        'status' => CustomDomainStatus::PendingVerification,
    ]);

    $this->actingAs($this->admin)
        ->get(route('website.index'))
        ->assertStatus(200)
        ->assertSee('www.brightfuture.com')
        ->assertSee('Configure DNS')
        ->assertSee('Verify Domain')
        ->assertSee('Install SSL Certificate')
        ->assertSee('Connection Complete')
        ->assertSee($domain->verification_token)
        ->assertSee(config('custom_domain.cname_target'));
});

test('the website settings page shows the live success state once a domain is verified and its SSL is active', function () {
    $plan = Plan::firstOrCreate(['key' => PlanKey::Exclusive], Plan::factory()->make(['key' => PlanKey::Exclusive])->toArray());
    Subscription::factory()->create(['school_id' => $this->school->id, 'plan_id' => $plan->id, 'status' => SubscriptionStatus::Active]);

    CustomDomain::factory()->create([
        'school_id' => $this->school->id,
        'domain' => 'www.brightfuture.com',
        'is_primary' => true,
        'status' => CustomDomainStatus::Verified,
        'ssl_status' => CustomDomainSslStatus::Active,
    ]);

    $this->actingAs($this->admin)
        ->get(route('website.index'))
        ->assertStatus(200)
        ->assertSee('Your website is live on www.brightfuture.com!');
});
