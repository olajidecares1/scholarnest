<?php

use App\Enums\PlanKey;
use App\Models\School;

/**
 * A school's address on the day it registers.
 *
 * Registration gives a school its subdomain immediately. Building the website
 * happens later, often much later, and in between the school's own address
 * answered "404 NOT FOUND" on a white page. A head teacher told to visit their
 * new address saw a broken platform: nothing said the address was right, that
 * their portal was already live at it, or what was missing.
 *
 * The address now answers for itself, and every school that registers from
 * here on gets that from its first minute.
 */
function newlyRegisteredSchool(PlanKey $plan = PlanKey::Standard): School
{
    $school = School::factory()->create(['name' => 'Top Excellence High-Flyers', 'is_active' => true]);
    activateSchool($school, $plan);

    // No website row at all, which is exactly what a school has before
    // anybody has opened the website manager.
    return $school->fresh();
}

beforeEach(function () {
    config([
        'custom_domain.tenant_base_domain' => 'akademicanest.com',
        'app.url' => 'https://akademicanest.com',
    ]);
});

test('a school with no website yet is not a 404 at its own address', function () {
    $school = newlyRegisteredSchool();

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertOk()
        ->assertSee($school->name)
        ->assertSee('Website coming soon');
});

test('and it points at the portal, which already works there', function () {
    $school = newlyRegisteredSchool();

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertOk()
        ->assertSee('/portal', false)
        ->assertSee('Go to the portal');
});

test('an unfinished site is never indexed as the school\'s web presence', function () {
    $school = newlyRegisteredSchool();

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertSee('name="robots" content="noindex"', false);
});

test('a website that exists but is still a draft behaves the same way', function () {
    $school = newlyRegisteredSchool();
    $school->website()->create(['hero_title' => 'Welcome', 'is_published' => false]);

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertOk()
        ->assertSee('Website coming soon');
});

test('publishing replaces it with the real website, and nothing else changes', function () {
    $school = newlyRegisteredSchool();
    $website = $school->website()->create(['hero_title' => 'Welcome to Top Excellence', 'is_published' => false]);

    $this->get("http://{$school->subdomain}.akademicanest.com/")->assertSee('Website coming soon');

    $website->update(['is_published' => true]);

    // What a request boundary does. TenantResolver is a scoped binding and
    // caches its answer, including the school's loaded website, for the life
    // of one request; a test makes two requests through one application, so
    // without this the second would be answered from the first one's cache.
    $this->app->forgetScopedInstances();

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertOk()
        ->assertSee($school->name)
        // The real website, not the holding page.
        ->assertDontSee('Website coming soon');
});

test('the marketing pages answer the same way rather than each 404ing', function () {
    $school = newlyRegisteredSchool();

    foreach (['/about', '/news', '/events', '/contact', '/admissions'] as $path) {
        $this->get("http://{$school->subdomain}.akademicanest.com{$path}")
            ->assertOk()
            ->assertSee('Website coming soon');
    }
});

test('a Basic school is still a 404, because it has no website to publish', function () {
    $school = newlyRegisteredSchool(PlanKey::Basic);

    // Basic buys no website and no address for one. The tenant resolver
    // refuses the host before this is reached; by the default path it is a
    // 404, and offering a "coming soon" page would be advertising something
    // the plan does not include.
    $this->get(route('public.school-website', $school))->assertNotFound();
});

test('an API client is still told there is no website here', function () {
    $school = newlyRegisteredSchool();

    $this->getJson("http://{$school->subdomain}.akademicanest.com/")
        ->assertNotFound()
        ->assertJsonPath('message', "{$school->name} has not published a website yet.");
});

test('the page wears the school\'s own badge and nobody else\'s', function () {
    $school = newlyRegisteredSchool();
    $other = activateSchool(School::factory()->create(['name' => 'Riverside Academy']), PlanKey::Standard)->fresh();

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertSee($school->name)
        ->assertDontSee($other->name);
});
