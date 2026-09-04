<?php

use App\Enums\UserRole;
use App\Models\AuditLog;
use App\Models\School;
use App\Models\User;
use Illuminate\Support\Facades\Cache;
use Illuminate\Support\Facades\Hash;
use Illuminate\Support\Facades\RateLimiter;

/**
 * The hidden Super Admin sign-in.
 *
 * The click sequence on the logo is a curtain. These tests are mostly about
 * what is behind it, because that is what actually protects the account: the
 * endpoint must behave identically whether the request came from the revealed
 * dialog or from somebody who found the URL, and finding the URL must get them
 * precisely nothing.
 */
function superAdminUser(string $password = 'correct-horse-battery'): User
{
    return User::factory()->create([
        'role' => UserRole::SuperAdmin,
        'school_id' => null,
        'password' => Hash::make($password),
        'is_active' => true,
        'email_verified_at' => now(),
    ]);
}

beforeEach(function () {
    RateLimiter::clear('login-ip|127.0.0.1');
});

/**
 * Rate-limiter counters live in the cache, which outlives an individual test.
 *
 * The per-IP counter deliberately survives a successful sign-in - that is what
 * stops an attacker resetting it at will - so the failed attempts these tests
 * make on purpose would otherwise still be counted against 127.0.0.1 when a
 * later file tries to log somebody in, and fail it for no visible reason.
 */
afterEach(function () {
    Cache::flush();
});

// -----------------------------------------------------------------------------
// 1. Nothing on the page advertises it
// -----------------------------------------------------------------------------

test('the registration page shows no Super Admin login entry point', function () {
    $response = $this->get(route('register'));

    // No nav item, no button, no anchor pointing at it. The hidden form's own
    // action necessarily names the endpoint - that is what a form does - and
    // the design does not depend on the URL staying secret: see the direct-URL
    // tests below, which are what actually hold the door.
    $response->assertOk()
        ->assertDontSee('Super Admin Login')
        ->assertDontSee('<a href="'.route('super-admin.login'), false);
});

test('the registration page still offers ordinary school registration', function () {
    // Hiding the Super Admin door must not hide the front one. The heading
    // is asserted by its form title rather than the panel headline, which the
    // design breaks across two lines.
    //
    // This page DOES now carry a sign-in link. It deliberately had none, and
    // that turned out to be the reason two schools ended up typing valid
    // credentials into the hidden Team dialog: it was the only login form on
    // the page. See 'the registration page offers a way to sign in'.
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('Register Your School')
        ->assertSee('Create School Account');
});

test('the dialog markup is present but hidden until the sequence runs', function () {
    // It ships with the page - there is no second request that would give the
    // game away - but x-show and x-cloak keep it out of sight.
    //
    // Identified by the dialog's own id rather than its wording, which is
    // presentation and has already changed once.
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('x-cloak', false)
        ->assertSee('super-admin-login-title', false)
        ->assertSee(route('super-admin.login'), false);
});

test('other auth pages do not carry the trigger at all', function () {
    // Opt-in per page, so the sequence does not quietly exist app-wide.
    // The shared sign-in page is gone entirely, so there is no other auth
    // screen left for the trigger to leak onto - "login" just redirects.
    $this->get(route('login'))->assertRedirect(route('register'));

    $school = School::factory()->create();

    // Same marker as the test above, so this cannot pass merely because some
    // wording changed on the dialog.
    $this->get(route('portal.admin.login', [$school, $school->portal_admin_token]))
        ->assertOk()
        ->assertDontSee('super-admin-login-title', false)
        ->assertDontSee(route('super-admin.login'), false);
});

// -----------------------------------------------------------------------------
// 5 / 6. Discovering the URL gets you nothing
// -----------------------------------------------------------------------------

test('visiting the Super Admin login URL directly reveals no login page', function () {
    $this->get(route('super-admin.login'))
        ->assertRedirect(route('login'));
});

test('posting straight at the endpoint still requires real credentials', function () {
    superAdminUser();

    $this->post(route('super-admin.login'), [
        'login' => 'nobody@example.com',
        'password' => 'guessing',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

test('triggering the sequence grants nothing on its own', function () {
    // The curtain opens; the door stays shut. No session, no cookie, nothing.
    $this->get(route('register'))->assertOk();

    $this->assertGuest();
});

// -----------------------------------------------------------------------------
// The role check: valid credentials are not enough
// -----------------------------------------------------------------------------

test('a Super Admin signs in and lands on the platform dashboard', function () {
    $admin = superAdminUser();

    $this->post(route('super-admin.login'), [
        'login' => $admin->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('super-admin.dashboard', absolute: false));

    $this->assertAuthenticatedAs($admin);
});

test('a School Admin with a valid password is refused at this door', function () {
    $school = School::factory()->create();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
        'password' => Hash::make('correct-horse-battery'),
        'is_active' => true,
    ]);

    // Their password is genuinely correct - it works on the shared sign-in
    // page - and it still gets them no session here.
    //
    // They are sent to the sign-in they actually wanted rather than told their
    // password is wrong. Two real schools reached this dialog by accident and
    // were turned away certain their credentials were broken, because the
    // registration page had no sign-in link and this was the only login form
    // on it.
    $this->post(route('super-admin.login'), [
        'login' => $schoolAdmin->email,
        'password' => 'correct-horse-battery',
    ])
        ->assertRedirect(route('portal.find.show'))
        ->assertSessionHas('status');

    $this->assertGuest();
});

test('a wrong password tells a stranger nothing, whoever the account belongs to', function () {
    $school = School::factory()->create();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
        'password' => Hash::make('correct-horse-battery'),
        'is_active' => true,
    ]);

    $superAdmin = superAdminUser();

    // THIS is the oracle that matters, and it is about the WRONG-password
    // path. Somebody guessing at addresses must not be able to learn from the
    // answer whether an account exists, or whether it is a Super Admin.
    $messages = collect([$schoolAdmin->email, $superAdmin->email, 'nobody@example.test'])
        ->map(function (string $email) {
            session()->forget('errors');

            $this->post(route('super-admin.login'), [
                'login' => $email,
                'password' => 'not-the-password',
            ]);

            return session('errors')->first('login');
        });

    expect($messages->unique())->toHaveCount(1);

    // The correct-password path is deliberately different now: somebody who
    // has just proved they hold the account's password already knows it
    // exists, because it is theirs. Telling them where to sign in leaks
    // nothing and rescues them from a dead end.
    $this->post(route('super-admin.login'), [
        'login' => $schoolAdmin->email,
        'password' => 'correct-horse-battery',
    ])->assertRedirect(route('portal.find.show'));
});

test('a deactivated Super Admin cannot sign in', function () {
    $admin = superAdminUser();
    $admin->update(['is_active' => false]);

    $this->post(route('super-admin.login'), [
        'login' => $admin->email,
        'password' => 'correct-horse-battery',
    ])->assertSessionHasErrors('login');

    $this->assertGuest();
});

// -----------------------------------------------------------------------------
// 7. The existing protections still apply
// -----------------------------------------------------------------------------

test('failed attempts at the hidden door are throttled', function () {
    $admin = superAdminUser();

    foreach (range(1, 5) as $ignored) {
        $this->post(route('super-admin.login'), [
            'login' => $admin->email,
            'password' => 'wrong',
        ]);
        session()->forget('errors');
    }

    // The sixth is refused for being too many, not for being wrong - and the
    // correct password does not get through either.
    $this->post(route('super-admin.login'), [
        'login' => $admin->email,
        'password' => 'correct-horse-battery',
    ])->assertSessionHasErrors('login');

    expect(session('errors')->first('login'))->toContain('seconds');
    $this->assertGuest();
});

test('a successful Super Admin sign-in is written to the audit log', function () {
    $admin = superAdminUser();

    $this->post(route('super-admin.login'), [
        'login' => $admin->email,
        'password' => 'correct-horse-battery',
    ]);

    expect(AuditLog::where('action', 'super-admin.login')->exists())->toBeTrue();
});

test('a failed Super Admin sign-in is written to the audit log too', function () {
    superAdminUser();

    $this->post(route('super-admin.login'), [
        'login' => 'intruder@example.com',
        'password' => 'guessing',
    ]);

    $entry = AuditLog::where('action', 'super-admin.login-failed')->first();

    // The attempted login is recorded, because for a failed attempt it is the
    // only trace there is.
    expect($entry)->not->toBeNull()
        ->and($entry->user_name)->toContain('intruder@example.com')
        ->and($entry->ip_address)->not->toBeNull();
});

test('a valid password used by the wrong role is logged as a refusal', function () {
    $school = School::factory()->create();

    $schoolAdmin = User::factory()->create([
        'role' => UserRole::SchoolAdmin,
        'school_id' => $school->id,
        'password' => Hash::make('correct-horse-battery'),
        'is_active' => true,
    ]);

    $this->post(route('super-admin.login'), [
        'login' => $schoolAdmin->email,
        'password' => 'correct-horse-battery',
    ]);

    // Worth its own action: someone holding a real password and trying it at
    // the Super Admin door is a different event from a wrong guess.
    expect(AuditLog::where('action', 'super-admin.login-refused')->exists())->toBeTrue();
});

test('the hidden form carries a CSRF token and sits in the web middleware group', function () {
    // Laravel's CSRF middleware short-circuits under tests, so a tokenless
    // POST cannot be used to prove enforcement here. What is checkable is that
    // the form emits a token and the route is in the group that verifies it.
    $this->get(route('register'))
        ->assertOk()
        ->assertSee('name="_token"', false);

    $middleware = app('router')->getRoutes()->getByName('super-admin.login')->gatherMiddleware();

    expect($middleware)->toContain('web')->toContain('guest');
});

test('the registration page offers a way to sign in', function () {
    // The absence of this link is what sent two schools hunting, and the only
    // login form on the page was the hidden Team dialog.
    $this->get(route('register'))
        ->assertOk()
        ->assertSee(route('portal.find.show'), false)
        ->assertSee('Already registered?');
});
