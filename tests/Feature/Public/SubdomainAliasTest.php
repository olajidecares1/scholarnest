<?php

use App\Enums\PlanKey;
use App\Models\School;

/**
 * A school reached at an address that is not its canonical subdomain.
 *
 * This is the bug behind "the subdomain works for some schools and not
 * others", and the reason it looked random is that it is not random at all:
 *
 *   Greenhill                 slug "greenhill", subdomain "greenhill"
 *   Vincent Martins College   slug "vincent-martins-college",
 *                             subdomain "vincentmartinscollege"
 *
 * A one-word school's two identifiers are the same string, so its subdomain
 * worked whichever one anybody used. A school with a space in its name has
 * two different strings, and only one of them resolved; the other was refused
 * by the label pattern before it ever reached a query, and answered 404.
 *
 * Both now work, and the non-canonical one redirects, so there is still only
 * ONE address serving a school.
 */
function aliasSchool(PlanKey $plan = PlanKey::Standard, string $name = 'Vincent Martins College'): School
{
    $school = School::factory()->create(['name' => $name, 'is_active' => true]);
    activateSchool($school, $plan);
    $school->website()->create(['hero_title' => 'Welcome to '.$name, 'is_published' => true]);

    return $school->fresh();
}

beforeEach(function () {
    config([
        'custom_domain.tenant_base_domain' => 'akademicanest.com',
        'app.url' => 'https://akademicanest.com',
    ]);
});

test('the hyphenated slug is a real address and sends you to the canonical one', function () {
    $school = aliasSchool();

    expect($school->slug)->toContain('-')
        ->and($school->subdomain)->not->toContain('-');

    $this->get("http://{$school->slug}.akademicanest.com/")
        ->assertRedirect("https://{$school->subdomain}.akademicanest.com/");
});

test('the redirect keeps the page you asked for, not just the front door', function () {
    $school = aliasSchool();

    $this->get("http://{$school->slug}.akademicanest.com/results?year=2026")
        ->assertRedirect("https://{$school->subdomain}.akademicanest.com/results?year=2026");
});

test('a form posted to the old address keeps its method, so nothing is silently lost', function () {
    $school = aliasSchool();

    // 301 invites the browser to retry as a GET, which throws the body away.
    $this->post("http://{$school->slug}.akademicanest.com/contact-message")
        ->assertStatus(308);
});

test('a school whose subdomain was never filled in is still served at its slug', function () {
    $school = aliasSchool();

    // The row the backfill in add_subdomain_to_schools_table never reached.
    // There is no canonical address to redirect to, so this one is served
    // where it stands rather than 404ing.
    School::withoutEvents(fn () => $school->forceFill(['subdomain' => null])->saveQuietly());

    $this->get("http://{$school->slug}.akademicanest.com/")
        ->assertOk()
        ->assertSee($school->name);
});

test('a Basic school is not redirected into an address it may not have', function () {
    $school = aliasSchool(PlanKey::Basic);

    // Basic has no website at all, so its slug must answer exactly as its
    // subdomain does, and not send anybody on to a second address that then
    // says something different.
    $this->get("http://{$school->slug}.akademicanest.com/")->assertNotFound();
});

test('a suspended school says so at both of its addresses', function () {
    $school = aliasSchool();
    $school->update(['is_active' => false]);

    $this->get("http://{$school->subdomain}.akademicanest.com/")->assertStatus(503);
    $this->get("http://{$school->slug}.akademicanest.com/")->assertStatus(503);
});

test('a hyphenated address belonging to nobody is still a 404', function () {
    aliasSchool();

    $this->get('http://no-such-school-at-all.akademicanest.com/')->assertNotFound();
});

test('a label that is not a hostname label at all costs no query', function () {
    // Leading and trailing hyphens are not legal in a DNS label, and neither
    // is an underscore. Refused at the pattern, as before.
    foreach (['-greenfield', 'greenfield-', 'green_field'] as $label) {
        $this->get("http://{$label}.akademicanest.com/")->assertNotFound();
    }
});

test('two schools cannot be reached at one address', function () {
    $first = aliasSchool(name: 'Saint Mary');
    $second = aliasSchool(name: 'Saint-Mary');

    // Stripping the hyphens makes these collide, so availableSubdomain() gave
    // the second a suffix. Each address must still lead to exactly one school.
    expect($first->subdomain)->not->toBe($second->subdomain);

    $this->get("http://{$first->subdomain}.akademicanest.com/")->assertOk()->assertSee($first->name);
    $this->get("http://{$second->subdomain}.akademicanest.com/")->assertOk()->assertSee($second->name);
});
