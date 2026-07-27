<?php

use App\Models\PageView;

test('visiting a page records a page view', function () {
    $this->get('/login');

    expect(PageView::where('path', '/login')->exists())->toBeTrue();
});

test('a referrer from a search engine is classified as search traffic', function () {
    $this->withHeaders(['referer' => 'https://www.google.com/search?q=edunest'])
        ->get('/login');

    $view = PageView::where('path', '/login')->latest()->first();

    expect($view->traffic_source)->toBe('search');
    expect($view->referrer_host)->toBe('www.google.com');
});

test('a request with no referrer is classified as direct traffic', function () {
    $this->get('/login');

    $view = PageView::where('path', '/login')->latest()->first();

    expect($view->traffic_source)->toBe('direct');
});

test('json requests are not tracked as page views', function () {
    $this->getJson('/login');

    expect(PageView::where('path', '/login')->exists())->toBeFalse();
});
