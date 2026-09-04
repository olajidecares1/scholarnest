<?php

use App\Enums\UserRole;
use App\Models\PageView;
use App\Models\User;

test('visiting a page records a page view', function () {
    $this->get(route('login'));

    expect(PageView::where('path', route('login', absolute: false))->exists())->toBeTrue();
});

test('a page view records the route name alongside the opaque path', function () {
    $this->get(route('login'));

    $view = PageView::where('path', route('login', absolute: false))->latest()->first();

    expect($view->route_name)->toBe('login');
    expect($view->label())->toBe('Login');
});

test('visiting the super admin panel is excluded from public traffic analytics', function () {
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)->get(route('super-admin.dashboard'));

    expect(PageView::where('route_name', 'super-admin.dashboard')->exists())->toBeFalse();
});

test('a referrer from a search engine is classified as search traffic', function () {
    $this->withHeaders(['referer' => 'https://www.google.com/search?q=scholarnest'])
        ->get(route('login'));

    $view = PageView::where('path', route('login', absolute: false))->latest()->first();

    expect($view->traffic_source)->toBe('search');
    expect($view->referrer_host)->toBe('www.google.com');
});

test('a request with no referrer is classified as direct traffic', function () {
    $this->get(route('login'));

    $view = PageView::where('path', route('login', absolute: false))->latest()->first();

    expect($view->traffic_source)->toBe('direct');
});

test('json requests are not tracked as page views', function () {
    $this->getJson(route('login'));

    expect(PageView::where('path', route('login', absolute: false))->exists())->toBeFalse();
});
