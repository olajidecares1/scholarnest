<?php

use App\Enums\CustomDomainStatus;
use App\Enums\PlanKey;
use App\Models\CustomDomain;
use App\Models\School;
use App\Models\SchoolWebsite;

/**
 * The application answers at the address in APP_URL.
 *
 * Reaching it by IP served the same site at a different host - and a session
 * cookie belongs to one host. Sign in at 127.0.0.1:8000, click any link the
 * application generated (route() builds those from APP_URL, so they point at
 * the real host) and the browser sends no cookie. You are signed out, with
 * nothing on screen saying why.
 *
 * The narrowness is the point. An unrecognised DOMAIN is left alone: it is not
 * a wrong way of reaching this application, it is a domain somebody pointed
 * here, and the tenant routing answers it with a 404 deliberately.
 */
beforeEach(function () {
    config(['app.url' => 'http://akademicnest.test']);
});

describe('an IP address is sent to the real address', function () {
    test('with the path and query kept', function () {
        $this->get('http://127.0.0.1/legal/privacy?from=email')
            ->assertRedirect('http://akademicnest.test/legal/privacy?from=email');
    });

    test('a non-standard port is dropped, not carried over', function () {
        // The port belongs to how the machine is served, not to the address the
        // application answers at.
        $this->get('http://127.0.0.1:8000/legal')
            ->assertRedirect('http://akademicnest.test/legal');
    });

    test('it is a 301, so the browser stops going there on its own', function () {
        $this->get('http://127.0.0.1/legal')->assertStatus(301);
    });

    test('IPv6 counts too', function () {
        $this->get('http://[::1]/legal')
            ->assertRedirect('http://akademicnest.test/legal');
    });

    test('the port from APP_URL is honoured when it has one', function () {
        config(['app.url' => 'http://akademicnest.test:8080']);

        $this->get('http://127.0.0.1/legal')
            ->assertRedirect('http://akademicnest.test:8080/legal');
    });
});

describe('the right host on the wrong port is normalised', function () {
    test('the port is dropped', function () {
        $this->get('http://akademicnest.test:8000/legal')
            ->assertRedirect('http://akademicnest.test/legal');
    });

    test('the canonical address itself is served, not redirected', function () {
        $this->get('http://akademicnest.test/legal')->assertOk();
    });
});

describe('what is deliberately left alone', function () {
    test('an unrecognised domain still 404s rather than being redirected', function () {
        // Turning this into a redirect would confirm to whoever pointed that
        // domain here that something is running at this address.
        $this->get('http://www.someone-elses-domain.com/')->assertNotFound();
    });

    test('a school subdomain is not touched', function () {
        config(['custom_domain.tenant_base_domain' => 'akademicnest.test']);

        $school = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $school->id, 'is_published' => true]);

        $this->get("http://{$school->subdomain}.akademicnest.test/")
            ->assertOk()
            ->assertSee($school->name);
    });

    test('a school custom domain is not touched', function () {
        $school = activateSchool(School::factory()->create(), PlanKey::Exclusive);
        SchoolWebsite::factory()->create(['school_id' => $school->id, 'is_published' => true]);
        CustomDomain::factory()->create([
            'school_id' => $school->id,
            'domain' => 'www.greenfield.com',
            'is_primary' => true,
            'status' => CustomDomainStatus::Verified,
        ]);

        $this->get('http://www.greenfield.com/')->assertOk();
    });

    test('a POST is never redirected, because that would drop the body', function () {
        // The form that sent it came from a page on this host anyway, so there
        // is nothing to correct - and a redirect here would silently discard
        // whatever was being submitted.
        $this->post('http://127.0.0.1/legal')->assertStatus(405);
    });

    test('the health check is left alone', function () {
        // Whatever monitors the machine hits it at 127.0.0.1, often with
        // something that does not follow redirects.
        $this->get('http://127.0.0.1/up')->assertOk();
    });

    test('nothing is redirected when APP_URL is itself an IP', function () {
        // A legitimate way to run this on a private network. Sending 127.0.0.1
        // to another IP would help nobody.
        config(['app.url' => 'http://192.168.1.50']);

        $this->get('http://127.0.0.1/legal')->assertOk();
    });

    test('nothing is redirected when APP_URL is unparseable', function () {
        config(['app.url' => 'not a url']);

        $this->get('http://127.0.0.1/legal')->assertOk();
    });
});
