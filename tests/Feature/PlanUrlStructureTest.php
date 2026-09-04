<?php

use App\Enums\PlanKey;
use App\Models\School;

/**
 * Which addresses a school has, decided by what it is paying for.
 *
 * The three tiers are three different shapes, not one shape with extras:
 *
 *   BASIC       everything on the platform host. A shared token front door,
 *               a landing page at /{portal_key}, portals under
 *               /p/{portal_key}/..., and NO public website at all.
 *
 *   STANDARD    its own subdomain. The website and all four portals move
 *               onto it.
 *
 *   EXCLUSIVE   the same again on the school's own domain, once verified.
 *
 * Result checking is the deliberate exception: it stays on the platform host
 * for every plan, so one address shape covers results whatever a school pays.
 */
function addressedSchoolOn(PlanKey $plan, bool $withPublishedWebsite = false): School
{
    $school = activateSchool(School::factory()->create(['name' => 'Greenfield College']), $plan);

    if ($withPublishedWebsite) {
        $school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);
    }

    return $school->fresh();
}

describe('Basic plan', function () {
    test('has no website address at all, rather than a broken one', function () {
        $school = addressedSchoolOn(PlanKey::Basic);

        // publicUrl() will happily build /p/{portal_key} for any school, and
        // PublicSchoolWebsiteController answers that with 404 below Standard.
        // The application was handing that address out - to the dashboard's
        // "School Website" button and to production:urls - as though it were
        // real. A school with no website produces no website URL.
        expect($school->hasPublicWebsite())->toBeFalse()
            ->and($school->websiteUrl())->toBeNull();
    });

    test('its front door is the portal landing at the site root', function () {
        $school = addressedSchoolOn(PlanKey::Basic);

        expect($school->frontDoorUrl())
            ->toBe(route('basic-portal.school', $school))
            ->toContain('/'.$school->portal_key);
    });

    test('its portals live under the platform host', function () {
        $school = addressedSchoolOn(PlanKey::Basic);

        expect($school->portalLoginUrl('staff'))
            ->toContain('/p/'.$school->portal_key.'/staff-portal/')
            ->not->toContain($school->subdomain.'.');
    });

    test('the landing page serves the portal hub', function () {
        $school = addressedSchoolOn(PlanKey::Basic);

        $this->get(route('basic-portal.school', $school))
            ->assertOk()
            ->assertSee($school->name);
    });
});

describe('Standard plan', function () {
    beforeEach(function () {
        config(['custom_domain.tenant_base_domain' => 'scholarnest.com.ng']);
    });

    test('its website is its own subdomain', function () {
        $school = addressedSchoolOn(PlanKey::Standard, withPublishedWebsite: true);

        expect($school->websiteUrl())->toContain('greenfieldcollege.scholarnest.com.ng');
    });

    test('the subdomain has no hyphens, whatever the school is called', function () {
        config(['custom_domain.tenant_base_domain' => 'scholarnest.com.ng']);

        $school = activateSchool(
            School::factory()->create(['name' => "Vincent Martin's College"]),
            PlanKey::Standard,
        )->fresh();

        expect($school->subdomain)->toBe('vincentmartinscollege')
            ->and($school->resolvedPublicHost())->toBe('vincentmartinscollege.scholarnest.com.ng');
    });

    test('its portals move onto the subdomain', function () {
        $school = addressedSchoolOn(PlanKey::Standard, withPublishedWebsite: true);

        expect($school->portalLoginUrl('staff'))
            ->toContain('greenfieldcollege.scholarnest.com.ng/staff-portal/')
            ->not->toContain('/p/'.$school->portal_key);
    });

    test('result checking stays on the platform host, unlike everything else', function () {
        $school = addressedSchoolOn(PlanKey::Standard, withPublishedWebsite: true);

        // Deliberate. A result link is printed on slips and pasted into
        // messages, and one address shape for every plan means a parent is
        // never guessing which kind of school they are dealing with.
        expect($school->resultLinkUrl())
            ->toContain('/'.$school->result_link_slug.'/result')
            ->not->toContain('greenfieldcollege.scholarnest.com.ng');
    });

    test('with no website published yet it falls back to the portal landing', function () {
        $school = addressedSchoolOn(PlanKey::Standard);

        // The plan includes a website; this school has not made one. Sending
        // a visitor to the empty shell of an unpublished site helps nobody,
        // so they get the page that tells them where to sign in.
        expect($school->websiteUrl())->toBeNull()
            ->and($school->frontDoorUrl())->toBe(route('basic-portal.school', $school));

        $this->get(route('basic-portal.school', $school))->assertOk();
    });

    test('with a website published, the landing redirects to it', function () {
        $school = addressedSchoolOn(PlanKey::Standard, withPublishedWebsite: true);

        $this->get(route('basic-portal.school', $school))
            ->assertRedirect($school->websiteUrl());
    });
});

test('a school that drops to Basic stops advertising the website it kept', function () {
    config(['custom_domain.tenant_base_domain' => 'scholarnest.com.ng']);

    $school = addressedSchoolOn(PlanKey::Standard, withPublishedWebsite: true);

    expect($school->websiteUrl())->not->toBeNull();

    // Downgraded. The published website ROW survives - nothing deletes it -
    // and without the plan check this would go on handing out an address the
    // public site now refuses to serve.
    $school->subscriptions()->delete();
    $school = activateSchool($school->fresh(), PlanKey::Basic)->fresh();

    expect($school->hasPublicWebsite())->toBeFalse()
        ->and($school->websiteUrl())->toBeNull()
        ->and($school->frontDoorUrl())->toBe(route('basic-portal.school', $school));
});

test('a school with no active plan has no public address at all', function () {
    $school = School::factory()->create();

    expect($school->websiteUrl())->toBeNull();

    // Not a redirect to somewhere, and not a page. 404 says nothing about
    // whether the school is unpaid, deactivated or simply not a customer.
    $this->get(route('basic-portal.school', $school))->assertNotFound();
});
