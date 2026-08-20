<?php

use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use App\Models\WebsiteBlock;
use App\Support\WebsiteBlockDefaults;

beforeEach(function () {
    $this->school = School::factory()->create();
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
    activateSchool($this->school);
});

function sampleBlock(array $overrides = []): array
{
    return array_merge([
        'section' => 'body',
        'type' => 'text',
        'content' => 'Hello world',
        'secondary_content' => null,
        'url' => null,
        'x' => 10, 'y' => 10, 'w' => 40, 'h' => 15,
        'style' => WebsiteBlockDefaults::defaultStyle('text'),
    ], $overrides);
}

test('a school admin can save blocks for a page and they round-trip', function () {
    $blocks = [
        sampleBlock(['section' => 'body', 'content' => 'Our story']),
        sampleBlock(['section' => 'body', 'type' => 'button', 'content' => 'Learn More', 'url' => '/about', 'style' => WebsiteBlockDefaults::defaultStyle('button')]),
    ];

    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => json_encode($blocks),
    ])->assertRedirect();

    $saved = WebsiteBlock::where('school_id', $this->school->id)->where('page', 'about')->get();
    expect($saved)->toHaveCount(2);
    expect($saved->firstWhere('type', 'text')->content)->toBe('Our story');
    expect($saved->firstWhere('type', 'button')->url)->toBe('/about');
});

test('saving blocks for one page does not affect another page', function () {
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'home'), [
        'blocks' => json_encode([sampleBlock(['section' => 'hero', 'content' => 'Home title'])]),
    ]);
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => json_encode([sampleBlock(['section' => 'body', 'content' => 'About text'])]),
    ]);

    expect(WebsiteBlock::where('school_id', $this->school->id)->where('page', 'home')->count())->toBe(1);
    expect(WebsiteBlock::where('school_id', $this->school->id)->where('page', 'about')->count())->toBe(1);
});

test('re-saving a page replaces its previous blocks rather than appending', function () {
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => json_encode([sampleBlock(['content' => 'First version'])]),
    ]);
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => json_encode([sampleBlock(['content' => 'Second version'])]),
    ]);

    $saved = WebsiteBlock::where('school_id', $this->school->id)->where('page', 'about')->get();
    expect($saved)->toHaveCount(1);
    expect($saved->first()->content)->toBe('Second version');
});

test('an invalid page name is rejected', function () {
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'not-a-real-page'), [
        'blocks' => json_encode([sampleBlock()]),
    ])->assertNotFound();
});

test('malformed blocks json is rejected', function () {
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => 'not-json',
    ])->assertSessionHasErrors('blocks');
});

test('a block missing required fields is rejected', function () {
    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => json_encode([['section' => 'body']]),
    ])->assertSessionHasErrors();
});

test('saving blocks for one school does not affect another school\'s blocks', function () {
    $otherSchool = School::factory()->create();
    WebsiteBlock::factory()->create(['school_id' => $otherSchool->id, 'page' => 'about', 'section' => 'body', 'content' => 'Other school content']);

    $this->actingAs($this->admin)->put(route('website.blocks.update', 'about'), [
        'blocks' => json_encode([sampleBlock(['content' => 'My content'])]),
    ]);

    expect(WebsiteBlock::where('school_id', $otherSchool->id)->first()->content)->toBe('Other school content');
});

test('website block defaults seed content from the existing school website fields', function () {
    $website = $this->school->website()->create(['hero_title' => 'Test School', 'about_text' => 'We are great.']);

    $defaults = WebsiteBlockDefaults::forAbout($website);
    $body = collect($defaults)->firstWhere('section', 'body');

    expect($body['content'])->toBe('We are great.');
});
