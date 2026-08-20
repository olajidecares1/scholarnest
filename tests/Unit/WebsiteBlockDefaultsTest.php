<?php

use App\Models\SchoolWebsite;
use App\Support\WebsiteBlockDefaults;

test('forHome seeds the hero title block from hero_title', function () {
    $website = new SchoolWebsite(['hero_title' => 'Marvel School']);

    $blocks = collect(WebsiteBlockDefaults::forHome($website));
    $title = $blocks->firstWhere('key', 'title');

    expect($title)->not->toBeNull();
    expect($title['section'])->toBe('hero');
    expect($title['content'])->toBe('Marvel School');
});

test('forHome omits the subtitle block when hero_subtitle is empty', function () {
    $website = new SchoolWebsite(['hero_title' => 'Marvel School']);

    $blocks = collect(WebsiteBlockDefaults::forHome($website));

    expect($blocks->firstWhere('key', 'subtitle'))->toBeNull();
});

test('forHome includes 6 default feature-icon cards', function () {
    $website = new SchoolWebsite(['hero_title' => 'Marvel School']);

    $blocks = collect(WebsiteBlockDefaults::forHome($website));
    $features = $blocks->where('section', 'feature-icons');

    expect($features)->toHaveCount(6);
    expect($features->first()['type'])->toBe('card');
});

test('forAbout seeds the body block from about_text', function () {
    $website = new SchoolWebsite(['about_text' => 'We are a great school.']);

    $blocks = collect(WebsiteBlockDefaults::forAbout($website));
    $body = $blocks->firstWhere('section', 'body');

    expect($body['content'])->toBe('We are a great school.');
});

test('forAdmissions creates one card block per admission step', function () {
    $website = new SchoolWebsite([
        'admissions_steps' => [
            ['title' => 'Submit Enquiry', 'description' => 'Reach out first.'],
            ['title' => 'Tour', 'description' => 'Visit campus.'],
        ],
    ]);

    $blocks = collect(WebsiteBlockDefaults::forAdmissions($website));
    $steps = $blocks->where('section', 'steps');

    expect($steps)->toHaveCount(2);
    expect($steps->first()['type'])->toBe('card');
    expect($steps->first()['content'])->toBe('Submit Enquiry');
    expect($steps->first()['secondary_content'])->toBe('Reach out first.');
});

test('forAdmissions creates one text block per requirement', function () {
    $website = new SchoolWebsite([
        'admissions_requirements' => ['Birth certificate', 'Passport photo'],
    ]);

    $blocks = collect(WebsiteBlockDefaults::forAdmissions($website));
    $requirements = $blocks->where('section', 'requirements');

    expect($requirements)->toHaveCount(2);
});

test('forContact only creates cards for fields that are set', function () {
    $website = new SchoolWebsite(['contact_phone' => '08012345678']);

    $blocks = collect(WebsiteBlockDefaults::forContact($website));

    expect($blocks)->toHaveCount(1);
    expect($blocks->first()['secondary_content'])->toBe('08012345678');
});

test('forFooter always includes a description block', function () {
    $website = new SchoolWebsite([]);

    $blocks = collect(WebsiteBlockDefaults::forFooter($website));

    expect($blocks->firstWhere('section', 'description'))->not->toBeNull();
});

test('defaultStyle returns distinct shapes for text, button, and card', function () {
    $text = WebsiteBlockDefaults::defaultStyle('text');
    $button = WebsiteBlockDefaults::defaultStyle('button');
    $card = WebsiteBlockDefaults::defaultStyle('card');

    expect($text)->toHaveKey('font_size');
    expect($button)->toHaveKey('bg_color');
    expect($button)->toHaveKey('padding_x');
    expect($card)->toHaveKey('bg_opacity');
});
