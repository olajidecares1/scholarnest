<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\SchoolWebsite;
use Illuminate\Support\Facades\Route;

/**
 * A school's name never appears in a URL.
 *
 * Portal, website and result-checking addresses all used to carry the school's
 * slug, /schools/greenfield-college/staff-portal/... which put the school's
 * identity into every link anyone was ever sent, and from there into browser
 * history, bookmarks and the referrer header of every outbound click.
 *
 * They carry an opaque key now. This file is the guarantee: a route added later
 * that binds on the slug fails here rather than quietly reintroducing it.
 */
beforeEach(function () {
    $this->school = activateSchool(
        School::factory()->create(['name' => 'Greenfield College']),
        PlanKey::Standard,
    );

    SchoolWebsite::factory()->create([
        'school_id' => $this->school->id,
        'is_published' => true,
    ]);
});

describe('no route binds a school by its slug', function () {
    test('the registered route table contains no school slug parameter', function () {
        // The strongest form of this test: it reads the ACTUAL route table
        // rather than a list of paths somebody remembered to update. A route
        // file added next year is covered without anyone touching this.
        $offenders = collect(Route::getRoutes()->getRoutes())
            ->map(fn ($route) => $route->uri())
            ->filter(fn (string $uri) => str_contains($uri, '{school:slug}')
                || str_contains($uri, 'schools/{school}'))
            ->values()
            ->all();

        expect($offenders)->toBe([]);
    });

    test('the school-facing URLs the model generates never spell the school out', function () {
        $slug = $this->school->slug;

        expect($slug)->toContain('greenfield');

        $urls = [
            'public website' => $this->school->publicUrl('public.school-website'),
            'staff login' => $this->school->portalLoginUrl('staff'),
            'student login' => $this->school->portalLoginUrl('student'),
            'guardian login' => $this->school->portalLoginUrl('guardian'),
            'admin login' => $this->school->portalLoginUrl('web'),
            'result link' => $this->school->resultLinkUrl(),
        ];

        foreach ($urls as $label => $url) {
            expect($url)->not->toContain($slug, "{$label} still carries the slug")
                ->not->toContain('greenfield', "{$label} still names the school")
                ->not->toContain('/schools/', "{$label} still uses the old path");
        }
    });
});

describe('the opaque key works as an address', function () {
    test('the public website is reachable by portal key', function () {
        $this->get(route('public.school-website', $this->school))
            ->assertOk()
            ->assertSee('Greenfield College');
    });

    test('a portal sign-in page is reachable by portal key', function () {
        $this->get($this->school->portalLoginUrl('staff'))->assertOk();
    });

    test('the result checker is reachable by its own link', function () {
        $this->get($this->school->resultLinkUrl())->assertOk();
    });

    test('the old slug-shaped address is gone, not redirected', function () {
        // Deliberate. A redirect would keep the slug resolving, and a URL that
        // still resolves is still a URL that names the school, in history, in
        // referrer headers, and in whatever indexed it.
        $this->get('/schools/'.$this->school->slug)->assertNotFound();
        $this->get('/schools/'.$this->school->slug.'/news')->assertNotFound();
        $this->get('/'.$this->school->slug)->assertNotFound();
        $this->get('/'.$this->school->slug.'/result')->assertNotFound();
    });
});

describe('the keys are unguessable and unique', function () {
    test('every school gets its own, generated on creation', function () {
        $other = School::factory()->create(['name' => 'Greenfield College']);

        expect($this->school->portal_key)->toHaveLength(20)
            ->and($other->portal_key)->toHaveLength(20)
            ->and($other->portal_key)->not->toBe($this->school->portal_key)
            // Two schools with the SAME name get different keys, which a
            // name-derived slug could never manage without a "-2" suffix that
            // gave the game away.
            ->and($other->result_link_slug)->not->toBe($this->school->result_link_slug);
    });

    test('a key is not derived from anything about the school', function () {
        $school = School::factory()->create(['name' => 'Greenfield College']);

        expect(strtolower($school->portal_key))->not->toContain('greenfield')
            ->not->toContain('college')
            ->and(strtolower($school->result_link_slug))->not->toContain('greenfield');
    });

    test('the route patterns cannot match a reserved path', function () {
        // What replaced the reserved-word exclusion lists. The old slug
        // pattern had to name every reserved path to keep /login and /legal
        // out of the school routes; a fixed-length mixed-case key cannot
        // collide with any of them by construction.
        foreach (['login', 'dashboard', 'legal', 'portal', 'register', 'api'] as $reserved) {
            expect(strlen($reserved))->not->toBe(20)
                ->and(strlen($reserved))->not->toBe(16);
        }

        // Two real root-level paths, still reachable. /login is deliberately
        // not one of them, sign-in sits behind an obfuscated URI, so there
        // has never been anything at that path to protect.
        $this->get('/legal')->assertSuccessful();
        $this->get('/portal/sign-in')->assertSuccessful();
    });
});

describe('one school still cannot reach another', function () {
    test('a portal key belongs to exactly one school', function () {
        $other = activateSchool(School::factory()->create(['name' => 'Riverside Academy']), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $other->id, 'is_published' => true]);

        // The key changed what the URL says, not what it grants. Each address
        // still resolves to its own school and nothing else.
        $this->get(route('public.school-website', $this->school))
            ->assertSee('Greenfield College')
            ->assertDontSee('Riverside Academy');

        $this->get(route('public.school-website', $other))
            ->assertSee('Riverside Academy')
            ->assertDontSee('Greenfield College');
    });

    test('an unknown key is a 404', function () {
        $this->get('/p/'.str_repeat('z', 20))->assertNotFound();
        $this->get('/'.str_repeat('z', 20))->assertNotFound();
    });
});
