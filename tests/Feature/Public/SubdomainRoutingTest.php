<?php

use App\Enums\PlanKey;
use App\Models\School;
use Illuminate\Support\Facades\URL;

function standardSchoolWithSubdomain(): School
{
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Standard);
    $school->website()->create(['hero_title' => 'Welcome to '.$school->name, 'is_published' => true]);

    return $school;
}

beforeEach(function () {
    config(['custom_domain.tenant_base_domain' => 'akademicanest.com']);
    // Pinned rather than left to whatever APP_URL happens to be in the local
    // .env, so this test's https-with-no-port expectations are deterministic
    // and don't accidentally pass/fail based on the developer's own setup.
    config(['app.url' => 'https://akademicanest.com']);
});

test('a standard-plan school\'s subdomain serves its public website', function () {
    $school = standardSchoolWithSubdomain();

    $this->get("http://{$school->subdomain}.akademicanest.com/")
        ->assertOk()
        ->assertSee($school->name);
});

test('an exclusive-plan school does not resolve as an akademicanest.com subdomain', function () {
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Exclusive);
    $school->website()->create(['is_published' => true]);

    $this->get("http://{$school->subdomain}.akademicanest.com/")->assertNotFound();
});

test('an unknown slug on the subdomain pattern returns 404', function () {
    $this->get('http://totally-unknown-school.akademicanest.com/')->assertNotFound();
});

test('an inactive standard-plan school does not resolve on its subdomain', function () {
    $school = standardSchoolWithSubdomain();
    $school->update(['is_active' => false]);

    $this->get("http://{$school->subdomain}.akademicanest.com/")->assertNotFound();
});

test('the default path redirects to the subdomain for a standard-plan school', function () {
    $school = standardSchoolWithSubdomain();

    $this->get(route('public.school-website', $school))
        ->assertRedirect("https://{$school->subdomain}.akademicanest.com/");
});

test('an unconfigured tenant base domain leaves the default path working as before', function () {
    config(['custom_domain.tenant_base_domain' => null]);
    $school = standardSchoolWithSubdomain();

    $this->get(route('public.school-website', $school))->assertOk();
    $this->get("http://{$school->subdomain}.akademicanest.com/")->assertNotFound();
});

test('the subdomain redirect and public URL use this app\'s own scheme and port, not a hardcoded https', function () {
    config(['app.url' => 'http://localhost:8081']);

    // The URL generator took its root from APP_URL at boot, so changing the
    // config alone leaves route() building http://localhost with no port -
    // which RedirectToCanonicalHost then correctly normalises to :8081 before
    // the tenant redirect under test ever runs. Moving the root as well is what
    // makes this test's own premise hold.
    URL::forceRootUrl(config('app.url'));

    $school = standardSchoolWithSubdomain();

    $this->get(route('public.school-website', $school))
        ->assertRedirect("http://{$school->subdomain}.akademicanest.com:8081/");

    expect($school->publicUrl('public.school-website'))
        ->toBe("http://{$school->subdomain}.akademicanest.com:8081/");
});

// -----------------------------------------------------------------------------
// The label itself
// -----------------------------------------------------------------------------

test('a subdomain never contains a hyphen', function () {
    // A subdomain is read aloud, typed from memory and printed on things.
    // "vincent-martins-college" is three chances to put a hyphen in the wrong
    // place; "vincentmartinscollege" is none.
    $school = School::factory()->create(['name' => 'Vincent Martins College']);

    expect($school->subdomain)->toBe('vincentmartinscollege')
        ->and($school->slug)->toBe('vincent-martins-college');
});

test('punctuation is dropped rather than turned into separators', function () {
    // An apostrophe and an ampersand are not said out loud, so they leave
    // nothing behind in an address people have to say out loud.
    expect(School::factory()->create(['name' => "GodStime Int'L School"])->subdomain)
        ->toBe('godstimeintlschool');

    expect(School::factory()->create(['name' => 'Arise & Shine Int School'])->subdomain)
        ->toBe('ariseshineintschool');
});

test('two schools that collide once hyphens are gone get different addresses', function () {
    // "Saint Mary" and "Saint-Mary" are distinct slugs and the same subdomain.
    // Something has to settle that at creation rather than leaving two schools
    // pointing at one website.
    $first = School::factory()->create(['name' => 'Saint Mary']);
    $second = School::factory()->create(['name' => 'Saint-Mary']);

    expect($first->subdomain)->toBe('saintmary')
        ->and($second->subdomain)->toBe('saintmary2')
        ->and($second->subdomain)->not->toBe($first->subdomain);
});

test('a school cannot claim a subdomain the platform uses itself', function () {
    // www.akademicanest.com belonging to a school would be a convincing place
    // to run a phishing page from.
    expect(School::factory()->create(['name' => 'WWW'])->subdomain)->not->toBe('www');
    expect(School::factory()->create(['name' => 'Mail'])->subdomain)->not->toBe('mail');
});

test('a name made only of punctuation still produces a usable address', function () {
    // The fallback is "school", which is itself reserved - so it comes out
    // suffixed. That is the two rules composing correctly rather than a quirk:
    // an empty label would be an unroutable school, and a reserved one would be
    // a school sitting at an address the platform uses.
    $subdomain = School::factory()->create(['name' => '!!!'])->subdomain;

    expect($subdomain)->toStartWith('school')
        ->not->toBe('school')
        ->and($subdomain)->toMatch('/^[a-z0-9]+$/');
});

test('the label stays within the 63 characters DNS allows', function () {
    // Longer is not merely ugly - it is not a valid hostname, and the school's
    // website would be unreachable.
    $school = School::factory()->create(['name' => str_repeat('Long School Name ', 8)]);

    expect(strlen($school->subdomain))->toBeLessThanOrEqual(63);
});

test('generation and resolution agree, which is the whole point of the column', function () {
    // The address the platform hands out has to be the address it answers at.
    // Building from one column and looking up by another would 404 every school
    // with more than one word in its name.
    $school = standardSchoolWithSubdomain();
    $school->update(['name' => 'Vincent Martins College']);

    $url = $school->fresh()->publicUrl('public.school-website');

    expect($url)->toContain($school->subdomain)
        ->not->toContain('-');

    $this->get(str_replace('https://', 'http://', $url))->assertOk();
});
