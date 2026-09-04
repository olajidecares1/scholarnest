<?php

use App\Enums\PlanKey;
use App\Models\School;

/**
 * The Basic-plan portal.
 *
 * Basic schools have no public website and no subdomain, so they cannot be
 * reached directly the way Standard and Exclusive schools are. Everyone
 * arrives at one shared, token-gated URL, names their school, and is forwarded
 * to that school's own page:
 *
 *     /{32-char token}  ->  "Enter your school name"  ->  /{school-slug}
 *
 * The policy is documented in docs/BASIC-PLAN-PORTAL.md.
 */
const PORTAL_TOKEN = 'abcdef0123456789abcdef0123456789';

beforeEach(function () {
    config(['basic_portal.token' => PORTAL_TOKEN]);
});

function basicSchool(string $name): School
{
    $school = School::factory()->create(['name' => $name]);
    activateSchool($school, PlanKey::Basic);

    return $school->fresh();
}

function nonBasicSchool(string $name, PlanKey $plan): School
{
    $school = School::factory()->create(['name' => $name]);
    activateSchool($school, $plan);

    return $school->fresh();
}

// -----------------------------------------------------------------------------
// The token guards the entry point
// -----------------------------------------------------------------------------

test('the finder opens with the correct token', function () {
    $this->get('/'.PORTAL_TOKEN)
        ->assertOk()
        ->assertSee('Enter your school name');
});

test('a wrong token is a 404, not a 403', function () {
    // 403 would confirm something real sits here and invite guessing.
    $this->get('/'.str_repeat('9', 32))->assertNotFound();
});

test('the portal does not exist at all when no token is configured', function () {
    config(['basic_portal.token' => null]);

    $this->get('/'.PORTAL_TOKEN)->assertNotFound();
});

test('a token of the wrong length does not even match the route', function () {
    $this->get('/'.str_repeat('a', 31))->assertNotFound();
    $this->get('/'.str_repeat('a', 33))->assertNotFound();
});

test('the token at the root does not swallow the application', function () {
    // The token now shares the root namespace with everything else, so this is
    // the check that matters most: real paths must still resolve.
    $this->get('/portal/sign-in')->assertOk();
    $this->get('/up')->assertOk();

    // /portal is the open front door now - name your school, pick your
    // portal - rather than a 404. It used to be reachable only at the
    // token-gated address above, which no school could be expected to keep.
    $this->get('/portal')->assertOk();
});

// -----------------------------------------------------------------------------
// Finding a school
// -----------------------------------------------------------------------------

test('an exact school name redirects to that school', function () {
    $school = basicSchool('Greenfield College');

    $this->post('/'.PORTAL_TOKEN, ['school' => 'Greenfield College'])
        ->assertRedirect(route('portal.index', $school));

    expect($school->slug)->toBe('greenfield-college');
});

test('the name is matched without caring about case or spacing', function () {
    $school = basicSchool('Greenfield College');

    $this->post('/'.PORTAL_TOKEN, ['school' => '  gREENFIELD college '])
        ->assertRedirect(route('portal.index', $school));
});

test('a school can also be found by its slug or school code', function () {
    $school = basicSchool('Greenfield College');

    $this->post('/'.PORTAL_TOKEN, ['school' => $school->slug])
        ->assertRedirect(route('portal.index', $school));

    $this->post('/'.PORTAL_TOKEN, ['school' => $school->school_code])
        ->assertRedirect(route('portal.index', $school));
});

test('a partial name finds the school', function () {
    $school = basicSchool('Greenfield College');

    $this->post('/'.PORTAL_TOKEN, ['school' => 'Greenfield'])
        ->assertRedirect(route('portal.index', $school));
});

test('several matches are listed to choose from rather than guessed at', function () {
    basicSchool('Kings College Lagos');
    basicSchool('Kings College Abuja');

    $response = $this->post('/'.PORTAL_TOKEN, ['school' => 'Kings College']);

    $response->assertOk()
        ->assertSee('Kings College Lagos')
        ->assertSee('Kings College Abuja')
        ->assertSee('Which is yours?', false);
});

test('an exact name wins over schools that merely contain it', function () {
    $exact = basicSchool('Kings College');
    basicSchool('Kings College Annexe');

    // Without tiered matching, the exact school would be buried in a list.
    $this->post('/'.PORTAL_TOKEN, ['school' => 'Kings College'])
        ->assertRedirect(route('portal.index', $exact));
});

test('an unknown school name is refused without saying why', function () {
    $response = $this->post('/'.PORTAL_TOKEN, ['school' => 'No Such School']);

    $response->assertRedirect()->assertSessionHasErrors('school');
    expect(session('errors')->first('school'))->toContain('find a school with that name');
});

test('the school name is required', function () {
    $this->post('/'.PORTAL_TOKEN, ['school' => ''])
        ->assertSessionHasErrors('school');
});

// -----------------------------------------------------------------------------
// Plan separation
// -----------------------------------------------------------------------------

test('a standard school is found here too, and lands on its portal', function () {
    $school = nonBasicSchool('Royal College', PlanKey::Standard);

    // This used to refuse them, on the reasoning that Standard schools have
    // their own subdomain and never need looking up. That left a Standard
    // school which had mislaid its subdomain with nowhere to type its own
    // name. Naming your school is the way in on every plan now; the PLAN
    // still decides which portals the hub then offers.
    $this->post('/'.PORTAL_TOKEN, ['school' => 'Royal College'])
        ->assertRedirect(route('portal.index', $school));
});

test('an exclusive school is found here too', function () {
    $school = nonBasicSchool('Elite Academy', PlanKey::Exclusive);

    $this->post('/'.PORTAL_TOKEN, ['school' => 'Elite Academy'])
        ->assertRedirect(route('portal.index', $school));
});

test('a school with no active subscription cannot be found', function () {
    School::factory()->create(['name' => 'Unpaid Academy']);

    $this->post('/'.PORTAL_TOKEN, ['school' => 'Unpaid Academy'])
        ->assertSessionHasErrors('school');
});

// -----------------------------------------------------------------------------
// The school's own page
// -----------------------------------------------------------------------------

test('a basic school page shows its portal sign-in choices', function () {
    $school = basicSchool('Greenfield College');

    // The two roles Basic actually has - School Admin and Teacher - plus the
    // result-token route for parents. It used to list Student and Parent
    // logins too, which on Basic opened straight onto the locked page.
    $this->get('/'.$school->portal_key)
        ->assertOk()
        ->assertSee('Greenfield College')
        ->assertSee('School Admin')
        ->assertSee('Staff / Teacher')
        ->assertSee('Check Result')
        ->assertDontSee('Parent / Guardian');
});

test('a standard school with a website is sent to it instead', function () {
    $school = nonBasicSchool('Royal College', PlanKey::Standard);
    $school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);

    // A school with its own front door does not also get a competing one at
    // the platform root.
    $this->get('/'.$school->portal_key)->assertRedirect($school->fresh()->websiteUrl());
});

test('a standard school with no website published yet gets the portal hub, not a redirect into nothing', function () {
    $school = nonBasicSchool('Royal College', PlanKey::Standard);

    // This used to redirect anyway, to an address the public site answers
    // with 404 - so a school that had not yet built its website sent every
    // visitor at its root to a dead page. There is nothing to redirect TO
    // until a site is published, so they get the page that tells them where
    // to sign in.
    $this->get('/'.$school->portal_key)
        ->assertOk()
        ->assertSee($school->name);
});

test('an unknown slug is a 404', function () {
    $this->get('/no-such-school-anywhere')->assertNotFound();
});

// -----------------------------------------------------------------------------
// The root-level route must not shadow the application
// -----------------------------------------------------------------------------

test('application paths still win over the school route', function () {
    // The school route is registered last and excludes reserved words. If it
    // ever started matching these, large parts of the app would disappear.
    $this->get('/portal/sign-in')->assertOk();
    $this->get('/up')->assertOk();
});

test('a school can never be given a reserved slug', function () {
    $school = School::factory()->create(['name' => 'Login']);

    // "login" is reserved, so the generated slug must differ from it.
    expect($school->slug)->not->toBe('login')
        ->and($school->slug)->toStartWith('login-');
});

test('a school whose name merely starts with a reserved word still works', function () {
    // "news" is a reserved slug. "Newspaper College" is a perfectly legitimate
    // school that happens to start with those letters, and must not be
    // collateral damage from the exclusion pattern.
    $school = basicSchool('Newspaper College');

    expect($school->slug)->toBe('newspaper-college');

    $this->get('/'.$school->portal_key)->assertOk();
});

test('a school can never be given a token-shaped slug', function () {
    // The token sits at the root and its route is registered first, so a
    // school whose slug were 32 unbroken alphanumeric characters would be
    // permanently unreachable - every request for it would hit the token
    // check and 404. Such slugs are refused and given a suffix instead.
    $thirtyTwoLetterName = str_repeat('a', 32);

    $school = School::factory()->create(['name' => $thirtyTwoLetterName]);
    activateSchool($school, PlanKey::Basic);

    expect($school->slug)->not->toBe($thirtyTwoLetterName)
        ->and($school->slug)->toBe($thirtyTwoLetterName.'-2');

    // And it must actually be reachable.
    $this->get('/'.$school->portal_key)->assertOk();
});

test('a school whose name produces an empty slug still gets a usable one', function () {
    // "!!!" survives Str::slug() as an empty string, which would otherwise
    // produce a school with no address at all.
    $school = School::factory()->create(['name' => '!!!']);

    $reserved = array_map('strtolower', config('basic_portal.reserved_slugs'));

    expect($school->slug)->not->toBe('')
        ->and($school->slug)->not->toBeIn($reserved)
        ->and($school->slug)->toMatch('/^[a-z0-9]+(?:-[a-z0-9]+)*$/');

    // And it must actually route.
    activateSchool($school, PlanKey::Basic);
    $this->get('/'.$school->portal_key)->assertOk();
});

// -----------------------------------------------------------------------------
// The finder must not become a way to list ScholarNest's customers
// -----------------------------------------------------------------------------

test('like wildcards typed into the box are treated as literal text', function () {
    basicSchool('Greenfield College');

    // Without escaping, "%" would match every school on the platform.
    $this->post('/'.PORTAL_TOKEN, ['school' => '%%'])
        ->assertSessionHasErrors('school');

    $this->post('/'.PORTAL_TOKEN, ['school' => '__'])
        ->assertSessionHasErrors('school');
});
