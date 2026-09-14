<?php

use App\Enums\PlanKey;
use App\Models\School;

/**
 * The way in, for every plan: name your school, then pick your portal.
 *
 *     /portal  ->  type the school's name  ->  that school's portal hub
 *                                              ->  the portals its plan includes
 *
 * This used to be Basic-only, behind a 32-character token nobody could be
 * expected to remember. Standard and Exclusive schools were assumed to arrive
 * at their own subdomain instead, true when they have the address to hand,
 * and no help at all when they do not.
 */
function findableSchool(string $name, PlanKey $plan): School
{
    return activateSchool(School::factory()->create(['name' => $name]), $plan)->fresh();
}

test('the front door needs no secret token', function () {
    $this->get(route('portal.find.show'))
        ->assertOk()
        ->assertSee('Enter your school name');
});

describe('naming a school takes you to its portal', function () {
    test('a Basic school', function () {
        $school = findableSchool('Greenfield College', PlanKey::Basic);

        $this->post(route('portal.find'), ['school' => 'Greenfield College'])
            ->assertRedirect(route('portal.index', $school));
    });

    test('a Standard school, which used to be invisible here', function () {
        $school = findableSchool('Vincent Martins College', PlanKey::Standard);

        // The finder returned Basic schools only. A Standard school that
        // mislaid its subdomain had nowhere to type its own name.
        $this->post(route('portal.find'), ['school' => 'Vincent Martins College'])
            ->assertRedirect(route('portal.index', $school));
    });

    test('a Standard school with a website still lands on the PORTAL', function () {
        $school = findableSchool('Websited Academy', PlanKey::Standard);
        $school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);

        // Somebody who asked for a portal wants the portal. Forwarding a
        // teacher to the school's marketing site because the school happens
        // to have one is not answering the question they asked.
        $this->post(route('portal.find'), ['school' => 'Websited Academy'])
            ->assertRedirect(route('portal.index', $school->fresh()));
    });

    test('a school with no active subscription is not found', function () {
        School::factory()->create(['name' => 'Unpaid Academy']);

        // There is nothing behind any of those doors yet, so naming it must
        // not lead anywhere.
        $this->post(route('portal.find'), ['school' => 'Unpaid Academy'])
            ->assertSessionHasErrors('school');
    });

    test('several matches are offered rather than guessed between', function () {
        findableSchool('Saint Mary College', PlanKey::Basic);
        findableSchool('Saint Mary Academy', PlanKey::Standard);

        $this->post(route('portal.find'), ['school' => 'Saint Mary'])
            ->assertOk()
            ->assertSee('Saint Mary College')
            ->assertSee('Saint Mary Academy');
    });
});

describe('the hub offers what the plan includes, and nothing else', function () {
    test('Basic gets staff and result checking, but no pupil or parent portal', function () {
        $school = findableSchool('Basic Academy', PlanKey::Basic);

        $this->get(route('portal.index', $school))
            ->assertOk()
            ->assertSee('Staff / Teacher')
            ->assertSee('Check Result')
            ->assertDontSee('Parent / Guardian')
            ->assertDontSee('Results, assignments');
    });

    test('Standard adds the pupil and parent portals', function () {
        $school = findableSchool('Standard Academy', PlanKey::Standard);

        $this->get(route('portal.index', $school))
            ->assertOk()
            ->assertSee('Staff / Teacher')
            ->assertSee('Student')
            ->assertSee('Parent / Guardian');
    });

    test('Exclusive gets the same three as Standard', function () {
        $school = findableSchool('Exclusive Academy', PlanKey::Exclusive);

        $this->get(route('portal.index', $school))
            ->assertOk()
            ->assertSee('Staff / Teacher')
            ->assertSee('Student')
            ->assertSee('Parent / Guardian');
    });
});

test('the old token-gated door still works', function () {
    // Schools may have kept that address. Opening a new one must not close it.
    $school = findableSchool('Greenfield College', PlanKey::Basic);

    config(['basic_portal.token' => $token = str_repeat('a', 32)]);

    $this->get(route('basic-portal.finder', $token))->assertOk();

    $this->post(route('basic-portal.find', $token), ['school' => 'Greenfield College'])
        ->assertRedirect(route('portal.index', $school));
});

test('the registration page points a returning school at it', function () {
    $this->get(route('register'))
        ->assertOk()
        ->assertSee(route('portal.find.show'), false)
        ->assertSee('Already registered?');
});

describe('finding a school by its registered email', function () {
    test('the exact email finds the school', function () {
        $school = findableSchool('Greenfield College', PlanKey::Basic);
        $school->update(['billing_email' => 'admin@greenfield.example']);

        $this->post(route('portal.find'), ['school' => 'admin@greenfield.example'])
            ->assertRedirect(route('portal.index', $school));
    });

    test('case does not matter', function () {
        $school = findableSchool('Greenfield College', PlanKey::Basic);
        $school->update(['billing_email' => 'admin@greenfield.example']);

        $this->post(route('portal.find'), ['school' => '  ADMIN@Greenfield.Example '])
            ->assertRedirect(route('portal.index', $school));
    });

    test('a partial email finds nothing, so the box cannot walk a domain', function () {
        $school = findableSchool('Greenfield College', PlanKey::Basic);
        $school->update(['billing_email' => 'admin@greenfield.example']);

        // Exact match only. A partial one would answer "who here uses this
        // domain?" and let somebody enumerate customers a domain at a time.
        $this->post(route('portal.find'), ['school' => '@greenfield.example'])
            ->assertSessionHasErrors('school');
    });

    test('the form says an email will do', function () {
        $this->get(route('portal.find.show'))
            ->assertOk()
            ->assertSee('School name or email');
    });
});
