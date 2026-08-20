<?php

use App\Enums\PlanKey;
use App\Models\School;

function standardSchoolWithSubdomain(): School
{
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Standard);
    $school->website()->create(['hero_title' => 'Welcome to '.$school->name, 'is_published' => true]);

    return $school;
}

beforeEach(function () {
    config(['custom_domain.tenant_base_domain' => 'ednumest.com']);
    // Pinned rather than left to whatever APP_URL happens to be in the local
    // .env, so this test's https-with-no-port expectations are deterministic
    // and don't accidentally pass/fail based on the developer's own setup.
    config(['app.url' => 'https://ednumest.com']);
});

test('a standard-plan school\'s subdomain serves its public website', function () {
    $school = standardSchoolWithSubdomain();

    $this->get("http://{$school->slug}.ednumest.com/")
        ->assertOk()
        ->assertSee($school->name);
});

test('an exclusive-plan school does not resolve as an ednumest.com subdomain', function () {
    $school = School::factory()->create(['is_active' => true]);
    activateSchool($school, PlanKey::Exclusive);
    $school->website()->create(['is_published' => true]);

    $this->get("http://{$school->slug}.ednumest.com/")->assertNotFound();
});

test('an unknown slug on the subdomain pattern returns 404', function () {
    $this->get('http://totally-unknown-school.ednumest.com/')->assertNotFound();
});

test('an inactive standard-plan school does not resolve on its subdomain', function () {
    $school = standardSchoolWithSubdomain();
    $school->update(['is_active' => false]);

    $this->get("http://{$school->slug}.ednumest.com/")->assertNotFound();
});

test('the default path redirects to the subdomain for a standard-plan school', function () {
    $school = standardSchoolWithSubdomain();

    $this->get(route('public.school-website', $school))
        ->assertRedirect("https://{$school->slug}.ednumest.com/");
});

test('an unconfigured tenant base domain leaves the default path working as before', function () {
    config(['custom_domain.tenant_base_domain' => null]);
    $school = standardSchoolWithSubdomain();

    $this->get(route('public.school-website', $school))->assertOk();
    $this->get("http://{$school->slug}.ednumest.com/")->assertNotFound();
});

test('the subdomain redirect and public URL use this app\'s own scheme and port, not a hardcoded https', function () {
    config(['app.url' => 'http://localhost:8081']);
    $school = standardSchoolWithSubdomain();

    $this->get(route('public.school-website', $school))
        ->assertRedirect("http://{$school->slug}.ednumest.com:8081/");

    expect($school->publicUrl('public.school-website'))
        ->toBe("http://{$school->slug}.ednumest.com:8081/");
});
