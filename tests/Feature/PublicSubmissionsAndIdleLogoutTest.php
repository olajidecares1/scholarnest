<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\ContactMessage;
use App\Models\MisconductReport;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\Staff;
use App\Models\User;
use App\Support\PortalLoginRedirect;
use Illuminate\Auth\Events\Login;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Cookie;

/**
 * Two bugs that turned out to be the same shape: something with nothing to do
 * with a portal session being decided by one.
 *
 *   1. A visitor's message from the public website was answered with a login
 *      page and never reached the database.
 *   2. An expired session sent people to SCHOOL REGISTRATION, as though the
 *      school itself did not exist, rather than to the login page for the
 *      portal they had been using.
 *
 * A NOTE ON THE ASSERTIONS. Every sensitive URI in this application is a
 * sha512 hash (see App\Support\SecureRoute), so route('register') contains the
 * string "register" nowhere at all. Asserting a redirect does "not contain
 * register" therefore passes whatever happens, and an earlier draft of this
 * file did exactly that and proved nothing. Compare against route() itself.
 *
 * AND ON THE IDLE CLOCK. The middleware stamps the session on the first
 * request it sees and only compares against that stamp on the next one. A test
 * that signs in, travels forward and then acts has no stamp to compare with,
 * it is a first request, so nothing has expired and the test passes for the
 * wrong reason. Every test below makes a real request first.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Isolation School']), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

describe('a visitor can write to the school without an account', function () {
    test('a message from a plain visitor is saved', function () {
        $this->post(route('public.contact-message.store', $this->school), [
            'name' => 'A Parent',
            'email' => 'parent@example.com',
            'message' => 'Please tell me about admissions for September.',
        ])->assertRedirect();

        expect(ContactMessage::where('school_id', $this->school->id)->count())->toBe(1);
    });

    test('and so is a conduct report', function () {
        $this->post(route('public.misconduct-report.store', $this->school), [
            'reporter_name' => 'A Neighbour',
            'description' => 'Two pupils in uniform were fighting at the bus stop this morning.',
            'attachments' => [UploadedFile::fake()->image('scene.jpg')],
        ])->assertRedirect();

        expect(MisconductReport::where('school_id', $this->school->id)->count())->toBe(1);
    });

    test('a visitor is never answered with the registration page', function () {
        $response = $this->post(route('public.contact-message.store', $this->school), [
            'name' => 'A Parent',
            'message' => 'A question about the school run.',
        ]);

        expect($response->headers->get('Location'))->not->toBe(route('register'));
    });

    test('THE BUG: an idle School Admin submitting the public form loses it', function () {
        // The actual report. The admin is signed in somewhere in the same
        // browser, they were looking at their own website, and filling in a
        // form takes longer than the three-minute idle timeout. The idle
        // middleware ran on the PUBLIC post, logged them out and answered with
        // the portal login, so the message was never written.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->travel(5)->minutes();

        $response = $this->post(route('public.contact-message.store', $this->school), [
            'name' => 'A Parent',
            'message' => 'Sent while an admin session happened to be open.',
        ]);

        expect(ContactMessage::where('school_id', $this->school->id)->count())->toBe(1)
            ->and($response->headers->get('Location'))->not->toBe($this->school->portalLoginUrl('web'));
    });

    test('the school comes from the URL, never from the form', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);

        $this->post(route('public.contact-message.store', $this->school), [
            'name' => 'A Parent',
            'message' => 'Intended for the school in the address bar.',
            // Ignored. School B must never receive School A's post.
            'school_id' => $other->id,
        ]);

        expect(ContactMessage::where('school_id', $this->school->id)->count())->toBe(1)
            ->and(ContactMessage::where('school_id', $other->id)->count())->toBe(0);
    });

    test('the School Admin can then see it', function () {
        $this->post(route('public.contact-message.store', $this->school), [
            'name' => 'A Parent',
            'message' => 'Please tell me about admissions.',
        ]);

        $this->actingAs($this->admin)
            ->get(route('inbox.index'))
            ->assertOk()
            ->assertSee('A Parent');
    });
});

describe('an expired session means sign in again, not register the school again', function () {
    test('THE BUG: a guest is sent to a login page, never to registration', function () {
        // FOLLOWED to the end. The old behaviour was TWO hops, the auth
        // middleware redirected to route('login'), and that route redirected
        // again to route('register'), so checking only the first Location
        // header said everything was fine while the browser still ended up on
        // the registration form.
        $this->followingRedirects()
            ->get(route('dashboard'))
            ->assertOk()
            ->assertDontSee('Register your school', false);

        expect($this->get(route('dashboard'))->headers->get('Location'))
            ->not->toBe(route('login'))
            ->not->toBe(route('register'));
    });

    test('a guest reaching a teacher page is sent to the TEACHER login', function () {
        // The school is in the URL, /schools/{school}/staff-portal/... so
        // it is known whatever the session says.
        $staff = Staff::factory()->create(['school_id' => $this->school->id]);

        expect($this->get(route('staff.dashboard', $this->school))->headers->get('Location'))
            ->toBe($this->school->portalLoginUrl('staff'));
    });

    test('signing in remembers the school and the portal', function () {
        // Not actingAs(), which sets the user directly and fires nothing. The
        // cookie is written by a listener on the Login event, so the event is
        // what has to happen.
        event(new Login('web', $this->admin, false));

        $cookie = collect(Cookie::getQueuedCookies())
            ->first(fn ($c) => $c->getName() === PortalLoginRedirect::COOKIE);

        expect($cookie)->not->toBeNull()
            ->and($cookie->getValue())->toBe("{$this->school->id}:web")
            // Not readable from JavaScript, and it outlives the session by design.
            ->and($cookie->isHttpOnly())->toBeTrue();
    });

    test('a School Admin browser that signed in here is remembered after the session goes', function () {
        // The admin dashboard is an obfuscated path with no school in it, so
        // without something outliving the session there is nothing left to say
        // which school this browser belongs to. Rule 2.4.
        //
        // Encrypted, because that is how the application will receive it,
        // EncryptCookies decrypts on the way in, and a plain-text one arrives
        // as null, which would land on the fallback and prove nothing.
        $this->withCookie(PortalLoginRedirect::COOKIE, "{$this->school->id}:web");

        expect($this->get(route('dashboard'))->headers->get('Location'))
            ->toBe($this->school->portalLoginUrl('web'));
    });

    test('and a browser with nothing remembered still gets a login page', function () {
        expect($this->get(route('dashboard'))->headers->get('Location'))
            ->toBe(route('portal.show'));
    });

    test('the remembered portal is not handed to a different one', function () {
        // A browser that last signed in as a teacher must not be given the
        // admin login just because it has a cookie with a school in it.
        // Encrypted, like the test above, an unreadable cookie would fall
        // back to the unified sign-in too, and pass for the wrong reason.
        $this->withCookie(PortalLoginRedirect::COOKIE, "{$this->school->id}:staff");

        expect($this->get(route('dashboard'))->headers->get('Location'))
            ->toBe(route('portal.show'));
    });

    test('an idle School Admin is sent to their own school portal login', function () {
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->travel(5)->minutes();

        $this->get(route('dashboard'))->assertRedirect($this->school->portalLoginUrl('web'));
    });

    test('an idle teacher is sent to the TEACHER login, not the admin one', function () {
        $staff = Staff::factory()->create(['school_id' => $this->school->id]);

        $this->actingAs($staff, 'staff')->get(route('staff.dashboard', $this->school))->assertOk();

        $this->travel(5)->minutes();

        $this->get(route('staff.dashboard', $this->school))
            ->assertRedirect($this->school->portalLoginUrl('staff'));
    });
});

describe('the public website never depends on a portal session', function () {
    test('an idle admin browsing the website is left alone', function () {
        // Rule 2.3 and 3: an expired portal session in one tab must not
        // navigate a public website tab anywhere at all.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();

        $this->travel(5)->minutes();

        $this->get(route('public.school-website', $this->school))
            ->assertOk()
            ->assertSee($this->school->name);
    });

    test('and a visitor with no session at all is too', function () {
        $this->travel(5)->minutes();

        $this->get(route('public.school-website', $this->school))->assertOk();
    });

    test('refreshing an idle website page reloads the website', function () {
        // Rule 1.2. Idle, then refresh, twice over, the second is the refresh
        // that used to be answered with a portal login.
        $this->get(route('public.school-website', $this->school))->assertOk();

        $this->travel(30)->minutes();

        $this->get(route('public.school-website', $this->school))
            ->assertOk()
            ->assertSee($this->school->name);
    });

    test('every public page stays public after a long idle, under every guard', function () {
        // Rule 1.1, exhaustively: the website is the website whoever is signed
        // in, and whether or not their portal session has expired.
        $guards = [
            'web' => $this->admin,
            'staff' => Staff::factory()->create(['school_id' => $this->school->id]),
        ];

        foreach ($guards as $guard => $user) {
            $this->actingAs($user, $guard);
            $this->travel(30)->minutes();

            foreach ([
                'public.school-website',
                'public.school-about.index',
                'public.school-news.index',
                'public.school-events.index',
                'public.school-contact.index',
            ] as $routeName) {
                $this->get(route($routeName, $this->school))->assertOk();
            }
        }
    });

    test('signing out of a portal leaves the website where it was', function () {
        // Rule 1.1 again, from the other side: a logout in one tab must not
        // navigate a website tab anywhere.
        $this->actingAs($this->admin)->get(route('dashboard'))->assertOk();
        $this->post(route('logout'));

        $this->get(route('public.school-website', $this->school))->assertOk();
    });

    test('the website is never behind an auth guard in the first place', function () {
        // The architectural version of the rule, and the one that stops this
        // coming back: no public website route may carry auth middleware, so
        // no session state can decide what it returns.
        $public = collect(app('router')->getRoutes())
            ->filter(fn ($route) => str_starts_with((string) $route->getName(), 'public.school-')
                || str_starts_with((string) $route->getName(), 'tenant.school-'));

        expect($public)->not->toBeEmpty();

        foreach ($public as $route) {
            foreach ($route->gatherMiddleware() as $middleware) {
                expect(is_string($middleware) && preg_match('/^auth(\.|:|$)/', $middleware) === 1)
                    ->toBeFalse("{$route->getName()} is behind auth middleware");
            }
        }
    });
});
