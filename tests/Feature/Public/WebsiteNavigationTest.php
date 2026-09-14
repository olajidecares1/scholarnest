<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\NavLink;
use App\Models\School;
use App\Models\SchoolFacility;
use App\Models\SchoolWebsite;
use App\Models\User;

/**
 * The menu across the top of a school's public website.
 *
 * Every item in it is an anchor to a section of the SAME page, so the menu and
 * the page have to agree: a menu item pointing at a section that is not
 * rendered would scroll a visitor nowhere at all.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Navigation Academy']), PlanKey::Standard);
    $this->website = SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

function navPage(School $school): string
{
    return test()->get(route('public.school-website', $school))->assertOk()->getContent();
}

/**
 * Just the <header>, which is where the menu lives.
 *
 * Asserting on the whole page cannot tell a menu item from any other link to
 * the same place: the footer's Quick Links column and the hero's call to
 * action point at these sections too, so "#academics is absent from the page"
 * is a different and much stronger claim than "it is absent from the menu",
 * and only the second one is true.
 */
function navHeader(School $school): string
{
    $html = navPage($school);
    $start = strpos($html, '<header');

    return substr($html, $start, strpos($html, '</header>') - $start);
}

describe('Facilities is off the menu but still on the page', function () {
    test('the default menu does not offer it', function () {
        // The href, not the word: "Facilities" still appears on the page as the
        // section's own heading, and asserting on the bare word would pass
        // whether or not the menu item was there.
        expect(navHeader($this->school))->not->toContain('#facilities')
            ->and(navHeader($this->school))->not->toContain('Facilities');
    });

    test('the section itself is still rendered, with its id intact', function () {
        // Off the menu is not off the site. Anything already linking to
        // #facilities, a printed flyer, another page, a school's own post,
        // has to keep working.
        SchoolFacility::factory()->create(['school_id' => $this->school->id, 'name' => 'Science Laboratory']);

        expect(navPage($this->school))->toContain('id="facilities"')
            ->toContain('Our Facilities')
            ->toContain('Science Laboratory');
    });

    test('the eight that remain are all there, and all point at real sections', function () {
        $header = navHeader($this->school);
        $page = navPage($this->school);

        foreach (['home', 'about', 'academics', 'admissions', 'news', 'events', 'gallery', 'contact'] as $section) {
            expect($header)->toContain('#'.$section)
                ->and($page)->toContain('id="'.$section.'"');
        }
    });

    test('the admin page no longer promises Facilities in the default menu', function () {
        // This copy lists what a school gets if it adds no links of its own.
        // Naming a menu item that is not there is worse than saying nothing.
        expect($this->actingAs($this->admin)->get(route('website.index'))->assertOk()->getContent())
            ->toContain('News, Events, Gallery, Contact')
            ->not->toContain('Events, Facilities, Gallery');
    });
});

describe('a school that writes its own menu keeps it', function () {
    test('custom links replace the default entirely', function () {
        NavLink::factory()->create([
            'school_id' => $this->school->id,
            'label' => 'Alumni',
            'url' => '/alumni',
        ]);

        $header = navHeader($this->school);

        expect($header)->toContain('Alumni')
            ->toContain('/alumni')
            // Facilities included: a school that wants it back can add it, and
            // nothing about taking it out of the default stops them.
            ->not->toContain('#academics');
    });

    test('one school never gets another school menu', function () {
        NavLink::factory()->create([
            'school_id' => $this->school->id, 'label' => 'Alumni', 'url' => '/alumni',
        ]);

        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $other->id, 'is_published' => true]);

        expect(navHeader($other))->not->toContain('Alumni');
    });
});
