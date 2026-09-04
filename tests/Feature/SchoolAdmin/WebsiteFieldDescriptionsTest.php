<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\User;

/**
 * Every control in the website builder says what it does.
 *
 * The person using this page runs a school, not a design studio. Half these
 * controls are named after CSS properties - letter spacing, opacity, text
 * transform, corner radius - and a label alone tells someone who does not
 * already know the term precisely nothing.
 *
 * The descriptions are written in terms of WHAT A VISITOR WILL SEE, never in
 * terms of the property being set, and they say where on the public website
 * the thing being edited actually appears.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Hinted Academy']), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

function builderPage(): string
{
    return test()->get(route('website.index'))->assertOk()->getContent();
}

describe('every field on the builder is described', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $this->html = builderPage();
    });

    test('the home tab explains the top bar, the hero and the events card', function () {
        expect($this->html)
            ->toContain('The thin strip above the main menu')
            ->toContain('A short greeting on the left of the strip')
            ->toContain('A small highlighted pill for a short announcement')
            ->toContain('where parents and staff sign in')
            ->toContain('Leave blank to use your ScholarNest login page')
            ->toContain('used only when the Hero Slider below is empty')
            ->toContain('fade from one to the next')
            ->toContain('lists your next few events from the Events page automatically');
    });

    test('the about tab explains each of the three statements separately', function () {
        // Mission, Vision and Values are three different questions and were
        // three identical empty boxes.
        expect($this->html)
            ->toContain('What your school sets out to do for its pupils')
            ->toContain('What your school is working towards becoming')
            ->toContain('The principles your school holds its pupils and staff to')
            ->toContain('Longer writing belongs on the About Us page tab');
    });

    test('the about photograph says where it actually shows', function () {
        // It no longer sits beside the About text; that card is gone. It sits
        // on the About tab and feeds the CONTACT section, which is confusing
        // enough that it now says so twice - once in the label and once at the
        // top of the hint - and names the field somebody was actually looking
        // for when they found this one.
        expect($this->html)->toContain('Photograph for the <strong>Contact</strong> section')
            ->toContain('This is not the About background')
            ->toContain('About section background')
            ->toContain('the Contact section borrows whichever image your hero is showing');
    });

    test('the contact fields say where they are used and what they become', function () {
        expect($this->html)
            ->toContain('the top bar, the Contact section, the footer and the Contact Us page')
            ->toContain('usually your school office, not a personal account')
            ->toContain('a number a visitor can tap to call')
            ->toContain('what the map on the Contact section searches for')
            ->toContain('Leave blank and no Facebook icon is shown');
    });

    test('the menu editor warns that one custom link replaces the whole menu', function () {
        // The trap: a school adds "Alumni" expecting it to join the standard
        // menu, and loses every other item.
        expect($this->html)->toContain('Add even one link and it replaces the standard menu entirely');
    });

    test('the gallery says what a visitor can do with the photographs', function () {
        expect($this->html)->toContain('Visitors can open any one full-screen')
            ->toContain('appears under the image in the gallery and in the full-screen viewer');
    });

    test('each page-builder section says what belongs in it', function () {
        expect($this->html)
            ->toContain('your history, your approach')
            ->toContain('What a parent has to do, in order')
            ->toContain('The documents and details a parent must bring')
            ->toContain('The wide band just above the footer');
    });
});

describe('the block inspector translates its CSS terms', function () {
    beforeEach(function () {
        $this->actingAs($this->admin);
        $this->html = builderPage();
    });

    test('typography terms are explained by what they look like', function () {
        expect($this->html)
            ->toContain('400 is normal, 700 is bold')
            ->toContain('The gap between individual letters')
            ->toContain('The gap between lines of a paragraph')
            ->toContain('without changing what you typed');
    });

    test('it warns where one control only works because of another', function () {
        // Border Color does nothing at width 0, and Blur does nothing at 100%
        // opacity. Both are silent no-ops that read as bugs.
        expect($this->html)
            ->toContain('Only visible while the width above is more than 0')
            ->toContain('Only has an effect while the opacity above is below 100%');
    });

    test('it says these settings are for one block, not the whole site', function () {
        expect($this->html)->toContain('It does not affect the rest of your website');
    });

    test('it flags the two things that hurt a visitor', function () {
        // A page that loads slowly and text nobody can read.
        expect($this->html)
            ->toContain('adds a little to how long it takes to load')
            ->toContain('pale text on a pale background is unreadable')
            ->toContain('hard to read on a phone');
    });
});

describe('the hints are marked up as hints', function () {
    test('they use small, which is what the element is for', function () {
        $html = $this->actingAs($this->admin)->get(route('website.index'))->getContent();

        expect(substr_count($html, '<small class="field-hint'))->toBeGreaterThan(40);
    });

    test('a hint is a block, so it does not run into whatever follows it', function () {
        // <small> is inline by default. Without this rule every hint would sit
        // on the same line as the next control.
        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain(".field-hint {\n    display: block;");
    });
});
