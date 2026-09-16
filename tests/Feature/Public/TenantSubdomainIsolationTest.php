<?php

use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\CustomDomain;
use App\Models\Examination;
use App\Models\ExaminationScore;
use App\Models\ExaminationSubject;
use App\Models\School;
use App\Models\Student;
use App\Models\User;
use App\Services\ResultTokenIssuer;
use App\Services\Tenancy\TenantResolution;
use App\Services\Tenancy\TenantResolver;

/**
 * School subdomains end to end: greenfield.akademicanest.com resolves to
 * Greenfield and only Greenfield, the platform stays on akademicanest.com, and
 * nothing a visitor can change in a URL or a form moves them into another
 * school.
 */
function tenantSchool(string $name, PlanKey $plan = PlanKey::Standard, bool $published = true): School
{
    $school = School::factory()->create(['name' => $name, 'is_active' => true]);
    activateSchool($school, $plan);
    $school->website()->create([
        'hero_title' => "Welcome to {$name}",
        'about_text' => "About {$name}",
        'is_published' => $published,
    ]);

    return $school->fresh();
}

function tenantUrl(School $school, string $path = '/'): string
{
    return "https://{$school->subdomain}.akademicanest.com{$path}";
}

beforeEach(function () {
    config([
        'custom_domain.tenant_base_domain' => 'akademicanest.com',
        'app.url' => 'https://akademicanest.com',
    ]);
});

// -----------------------------------------------------------------------------
// Resolution
// -----------------------------------------------------------------------------

test('the main domain is the platform, never a tenant', function () {
    $resolution = app(TenantResolver::class)->resolve('akademicanest.com');

    expect($resolution->status)->toBe(TenantResolution::CENTRAL)
        ->and($resolution->school)->toBeNull();

    // The platform's own front door, on the host APP_URL named at boot.
    $this->get('/')->assertRedirect();
});

test('a standard school and an exclusive school each resolve on their own subdomain', function () {
    $standard = tenantSchool('Greenfield International School');
    $exclusive = tenantSchool('Bright Future College', PlanKey::Exclusive);

    expect($standard->subdomain)->toBe('greenfieldinternationalschool')
        ->and($exclusive->subdomain)->toBe('brightfuturecollege');

    $this->get(tenantUrl($standard))->assertOk()->assertSee('Greenfield International School')->assertDontSee('Bright Future College');
    $this->get(tenantUrl($exclusive))->assertOk()->assertSee('Bright Future College')->assertDontSee('Greenfield International School');
});

test('the same page on two subdomains shows two different schools', function () {
    $greenfield = tenantSchool('Greenfield School');
    $bright = tenantSchool('Bright Future School');

    $this->get(tenantUrl($greenfield, '/about'))
        ->assertOk()
        ->assertSee('Greenfield School')
        ->assertDontSee('Bright Future School');

    $this->get(tenantUrl($bright, '/about'))
        ->assertOk()
        ->assertSee('Bright Future School')
        ->assertDontSee('Greenfield School');
});

test('lookups are case-insensitive, as hostnames are', function () {
    $school = tenantSchool('Greenfield School');

    expect(app(TenantResolver::class)->schoolFor(strtoupper($school->subdomain).'.AkademicaNest.com.')?->is($school))->toBeTrue();
});

test('an unknown subdomain is a 404 status page', function () {
    tenantSchool('Greenfield School');

    $this->get('https://doesnotexist.akademicanest.com/')
        ->assertNotFound()
        ->assertSee('No school website at this address')
        ->assertDontSee('Greenfield School');
});

test('a nested or malformed label is not a school', function () {
    $school = tenantSchool('Greenfield School');

    $this->get("https://evil.{$school->subdomain}.akademicanest.com/")->assertNotFound();
    $this->get('https://green-field.akademicanest.com/')->assertNotFound();
});

test('www goes to the platform rather than being treated as a school', function () {
    $this->get('https://www.akademicanest.com/pricing?x=1')
        ->assertRedirect('https://akademicanest.com/pricing?x=1')
        ->assertStatus(301);
});

test('a basic school gets no website on a subdomain', function () {
    $school = tenantSchool('Basic Academy', PlanKey::Basic);

    $this->get(tenantUrl($school))
        ->assertNotFound()
        ->assertDontSee('Basic Academy');

    expect($school->subdomainHost())->toBeNull()
        ->and($school->websiteUrl())->toBeNull();
});

test('an expired school shows the unavailable page and keeps its data', function () {
    $school = tenantSchool('Lapsed College');
    $school->activeSubscription->update(['status' => SubscriptionStatus::Expired]);

    $this->get(tenantUrl($school))
        ->assertStatus(503)
        ->assertSee('temporarily unavailable')
        ->assertDontSee('Lapsed College');

    expect(School::find($school->id)->website)->not->toBeNull();
});

test('a suspended school shows the unavailable page', function () {
    $school = tenantSchool('Suspended College');
    $school->update(['is_active' => false]);

    $this->get(tenantUrl($school, '/news'))->assertStatus(503);
});

// -----------------------------------------------------------------------------
// Addresses the application hands out
// -----------------------------------------------------------------------------

test('an exclusive school without a verified domain is given its subdomain', function () {
    $school = tenantSchool('Exclusive Academy', PlanKey::Exclusive);

    expect($school->websiteUrl())->toBe("https://{$school->subdomain}.akademicanest.com/")
        ->and($school->subdomainUrl())->toBe("https://{$school->subdomain}.akademicanest.com/");

    $this->get(route('public.school-about.index', $school))
        ->assertRedirect("https://{$school->subdomain}.akademicanest.com/about");
});

test('an exclusive school with a verified domain keeps it, and its subdomain still works', function () {
    $school = tenantSchool('Exclusive Academy', PlanKey::Exclusive);
    CustomDomain::factory()->create([
        'school_id' => $school->id,
        'domain' => 'exclusiveacademy.com',
        'status' => CustomDomainStatus::Verified,
        'is_primary' => true,
    ]);
    $school = $school->fresh();

    expect($school->resolvedPublicHost())->toBe('exclusiveacademy.com');

    $this->get('https://exclusiveacademy.com/')->assertOk()->assertSee('Exclusive Academy');
    $this->get(tenantUrl($school))->assertOk()->assertSee('Exclusive Academy');
});

test('links on a school subdomain stay on that subdomain', function () {
    $school = tenantSchool('Greenfield School');

    $this->get(tenantUrl($school))
        ->assertOk()
        ->assertSee("https://{$school->subdomain}.akademicanest.com/about", false)
        ->assertSee("https://{$school->subdomain}.akademicanest.com/build/", false)
        ->assertDontSee('/p/'.$school->portal_key, false);
});

// -----------------------------------------------------------------------------
// Result checking on the subdomain
// -----------------------------------------------------------------------------

/**
 * @return array{0: School, 1: Examination, 2: Student}
 */
function tenantSchoolWithResult(string $name): array
{
    $school = tenantSchool($name);

    $examination = Examination::factory()->create([
        'school_id' => $school->id, 'class_name' => 'JSS 1', 'term' => 'first', 'session' => '2026/2027',
    ]);
    $subject = ExaminationSubject::factory()->create(['examination_id' => $examination->id, 'max_score' => 100]);
    $student = Student::factory()->create(['school_id' => $school->id, 'class_name' => 'JSS 1', 'is_active' => true]);
    ExaminationScore::factory()->create(['examination_subject_id' => $subject->id, 'student_id' => $student->id, 'score' => 80]);

    return [$school, $examination, $student];
}

function tenantTokenFor(School $school, Student $student, Examination $examination): string
{
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    return app(ResultTokenIssuer::class)->issue($school, $student, $examination, $admin)['plain'];
}

test('results open on the school subdomain and stay there through each step', function () {
    [$school, , $student] = tenantSchoolWithResult('Greenfield School');

    $this->get(tenantUrl($school, '/results'))
        ->assertOk()
        ->assertSee('Greenfield School')
        ->assertSee('action="/results/identify"', false);

    $this->post(tenantUrl($school, '/results/identify'), ['admission_number' => $student->admission_number])
        ->assertRedirect('/results/confirm');

    $this->get(tenantUrl($school, '/results/confirm'))
        ->assertOk()
        ->assertSee($student->first_name ?? $student->admission_number);
});

test('a subdomain only considers its own school\'s pupils and tokens', function () {
    [$greenfield, $examination, $pupil] = tenantSchoolWithResult('Greenfield School');
    [$bright, , $brightPupil] = tenantSchoolWithResult('Bright Future School');

    // Greenfield's pupil is not found at Bright Future's address.
    $this->post(tenantUrl($bright, '/results/identify'), ['admission_number' => $pupil->admission_number])
        ->assertSessionHasErrors('admission_number');

    // Nor does Greenfield's token open anything there.
    $token = tenantTokenFor($greenfield, $pupil, $examination);
    $this->post(tenantUrl($bright, '/results/identify'), ['admission_number' => $brightPupil->admission_number]);
    $this->post(tenantUrl($bright, '/results'), ['code' => $token])->assertSessionHasErrors('code');
});

test('a token redeems on its own school subdomain', function () {
    [$school, $examination, $student] = tenantSchoolWithResult('Greenfield School');
    $token = tenantTokenFor($school, $student, $examination);

    $this->post(tenantUrl($school, '/results/identify'), ['admission_number' => $student->admission_number]);

    $response = $this->post(tenantUrl($school, '/results'), ['code' => $token]);

    $response->assertSessionHasNoErrors()->assertRedirect();
    expect($response->headers->get('Location'))->toStartWith(tenantUrl($school, '/results/view/'));
});

test('a basic school has no results page on a subdomain', function () {
    $school = tenantSchool('Basic Academy', PlanKey::Basic);

    $this->get(tenantUrl($school, '/results'))->assertNotFound();
});

// -----------------------------------------------------------------------------
// The platform stays on the platform
// -----------------------------------------------------------------------------

test('platform pages on a school subdomain are sent to the main domain', function () {
    $school = tenantSchool('Greenfield School');
    $registerPath = parse_url(route('register'), PHP_URL_PATH);
    $consolePath = parse_url(route('super-admin.dashboard'), PHP_URL_PATH);

    $this->get(tenantUrl($school, $registerPath))->assertRedirect('https://akademicanest.com'.$registerPath);
    $this->get(tenantUrl($school, $consolePath))->assertRedirect('https://akademicanest.com'.$consolePath);
    $this->post(tenantUrl($school, $registerPath), [])->assertNotFound();
});

test('an account from another school is refused on this school\'s subdomain', function () {
    $greenfield = tenantSchool('Greenfield School');
    $bright = tenantSchool('Bright Future School');
    $brightAdmin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $bright->id]);

    $this->actingAs($brightAdmin)->get(tenantUrl($greenfield))->assertForbidden();
    $this->actingAs($brightAdmin)->get(tenantUrl($bright))->assertOk();
});

test('the super admin is refused on a school subdomain', function () {
    $school = tenantSchool('Greenfield School');
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)->get(tenantUrl($school))->assertForbidden();
});

// -----------------------------------------------------------------------------
// Super Admin: seeing and changing the address
// -----------------------------------------------------------------------------

test('the super admin school page shows the address, plan and status', function () {
    $school = tenantSchool('Greenfield School');
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)->get(route('super-admin.schools.show', $school))
        ->assertOk()
        ->assertSee($school->slug)
        ->assertSee("https://{$school->subdomain}.akademicanest.com/")
        ->assertSee('Live');
});

test('the super admin can change a subdomain, and the old one stops resolving', function () {
    $school = tenantSchool('Greenfield School');
    $old = $school->subdomain;
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    $this->actingAs($superAdmin)
        ->post(route('super-admin.schools.subdomain', $school), ['subdomain' => 'Greenfield'])
        ->assertSessionHasNoErrors();

    expect($school->fresh()->subdomain)->toBe('greenfield')
        ->and(AuditLog::where('action', 'school.subdomain_changed')->where('subject_id', $school->id)->exists())->toBeTrue();

    auth()->logout();

    $this->get("https://{$old}.akademicanest.com/")->assertNotFound();
    $this->get('https://greenfield.akademicanest.com/')->assertOk()->assertSee('Greenfield School');
});

test('a subdomain cannot be changed to a taken, reserved or invalid label', function () {
    $school = tenantSchool('Greenfield School');
    $other = tenantSchool('Bright Future School');
    $superAdmin = User::factory()->create(['role' => UserRole::SuperAdmin, 'school_id' => null]);

    foreach ([$other->subdomain, 'www', 'admin', 'green-field', 'green.field', str_repeat('a', 64), ''] as $label) {
        $this->actingAs($superAdmin)
            ->post(route('super-admin.schools.subdomain', $school), ['subdomain' => $label])
            ->assertSessionHasErrors('subdomain');
    }

    expect($school->fresh()->subdomain)->toBe('greenfieldschool');
});

test('a school admin cannot change a subdomain', function () {
    $school = tenantSchool('Greenfield School');
    $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $school->id]);

    $this->actingAs($admin)->post(route('super-admin.schools.subdomain', $school), ['subdomain' => 'hijacked']);

    expect($school->fresh()->subdomain)->toBe('greenfieldschool');
});
