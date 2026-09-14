<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Models\WebsiteBlock;
use App\Support\BrandColorScale;
use App\Support\WebsiteBlockDefaults;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
    $this->school->website()->create(['hero_title' => 'Welcome to Our School', 'about_text' => 'A great place to learn.']);
    $this->actingAs($this->admin)->post(route('website.publish'));
});

test('the public homepage renders the default blocks when nothing has been saved', function () {
    expect(WebsiteBlock::where('school_id', $this->school->id)->count())->toBe(0);

    $this->get(route('public.school-website', $this->school))
        ->assertStatus(200)
        ->assertSee('Welcome to Our School');
});

test('the public about page renders the default blocks when nothing has been saved', function () {
    $this->get(route('public.school-about.index', $this->school))
        ->assertStatus(200)
        ->assertSee('A great place to learn.');
});

test('the public admissions page renders without error when nothing has been saved', function () {
    $this->get(route('public.school-admissions.index', $this->school))->assertStatus(200);
});

test('the public contact page renders without error when nothing has been saved', function () {
    $this->get(route('public.school-contact.index', $this->school))->assertStatus(200);
});

test('a saved hero block no longer replaces the designed hero', function () {
    // THE RULE CHANGED, and deliberately. The home hero used to be swapped out
    // for page-builder blocks the moment a school had saved one, and those
    // are placed by absolute coordinate, so they collided into overlapping
    // text at any width but the one they were arranged at. A school that had
    // touched the builder once got that instead of the design, permanently.
    //
    // The designed template is now what every school gets. The words are still
    // theirs, from their website settings; the LAYOUT is fixed, because that
    // is the part a coordinate system was never going to get right across four
    // breakpoints.
    WebsiteBlock::where('school_id', $this->school->id)->where('page', 'home')->delete();

    $style = WebsiteBlockDefaults::defaultStyle('text');
    $style['font_size'] = 72;

    WebsiteBlock::factory()->create([
        'school_id' => $this->school->id,
        'page' => 'home',
        'section' => 'hero',
        'type' => 'text',
        'content' => 'Custom Hero Title',
        'x' => 6, 'y' => 32, 'w' => 54, 'h' => 16,
        'style' => $style,
    ]);

    $this->get(route('public.school-website', $this->school))
        ->assertStatus(200)
        ->assertDontSee('Custom Hero Title')
        ->assertDontSee('font-size:72px', false)
        // The school's own hero title, from its settings, is what shows.
        ->assertSee($this->school->name);
});

test('a custom saved block is reflected on the public about page', function () {
    WebsiteBlock::factory()->create([
        'school_id' => $this->school->id,
        'page' => 'about',
        'section' => 'body',
        'type' => 'text',
        'content' => 'A completely custom about paragraph.',
        'x' => 10, 'y' => 10, 'w' => 80, 'h' => 60,
        'style' => WebsiteBlockDefaults::defaultStyle('text'),
    ]);

    $this->get(route('public.school-about.index', $this->school))
        ->assertStatus(200)
        ->assertSee('A completely custom about paragraph.')
        ->assertDontSee('A great place to learn.');
});

test('the public page head contains a brand color override when set', function () {
    $this->actingAs($this->admin)->put(route('website.update-brand-color'), [
        'brand_primary_color' => '#ff0000',
    ]);

    $expected = BrandColorScale::fromHex('#ff0000')['600'];

    $this->get(route('public.school-website', $this->school))
        ->assertStatus(200)
        ->assertSee("--color-primary-600: {$expected};", false);
});

test('the public page head has no brand color style block when unset', function () {
    $this->get(route('public.school-website', $this->school))
        ->assertStatus(200)
        ->assertDontSee('--color-primary-600:', false);
});
