<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Support\SchoolContact;

/**
 * The school's address, shown as a map.
 *
 * One component behind both places it appears, because a school's own website
 * disagreeing with itself about where the school is would be worse than either
 * answer alone.
 */
beforeEach(function () {
    $this->school = activateSchool(
        School::factory()->create(['name' => 'Greenfield College']),
        PlanKey::Standard,
    );

    $this->school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);
});

test('the home page maps the address a school has set', function () {
    $this->school->update(['contact_address' => '12 Ogui Road, Enugu']);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('google.com/maps?q='.urlencode('12 Ogui Road, Enugu'), false);
});

test('the Contact Us page maps it too, and prints the address beside it', function () {
    // This page carried no address and no map at all, only the home page did,
    // and this is the page somebody opens looking for exactly that.
    $this->school->update(['contact_address' => '12 Ogui Road, Enugu']);

    $this->get(route('public.school-contact.index', $this->school))
        ->assertOk()
        ->assertSee('Find Us')
        ->assertSee('12 Ogui Road, Enugu')
        ->assertSee('google.com/maps?q='.urlencode('12 Ogui Road, Enugu'), false)
        ->assertSee('Get directions');
});

test('no address means no map, rather than a map of the wrong place', function () {
    // It used to fall back to the school's NAME, which produces a map of
    // wherever Google decides that name is. A map that is confidently wrong on
    // a school's own website is worse than none: a parent drives to it.
    $this->school->update(['contact_address' => null]);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertDontSee('google.com/maps?q=', false);

    $this->get(route('public.school-contact.index', $this->school))
        ->assertOk()
        ->assertDontSee('Find Us');
});

test('the address set in School Settings wins over the website copy', function () {
    // Settings is on every plan; the website record is Standard and above.
    $this->school->update(['contact_address' => '12 Ogui Road, Enugu']);
    $this->school->website->update(['contact_address' => 'An older address']);

    // Asserted against the MAP, not the whole page: the website's own contact
    // blocks may still print their copy elsewhere, and that is a separate
    // display with its own rules.
    $this->get(route('public.school-contact.index', $this->school))
        ->assertOk()
        ->assertSee('google.com/maps?q='.urlencode('12 Ogui Road, Enugu'), false)
        ->assertDontSee('google.com/maps?q='.urlencode('An older address'), false);
});

test('a Basic school gets the map from Settings alone', function () {
    // A Basic school has no website record to hold an address, so this proves
    // the map reads the school itself.
    $basic = activateSchool(School::factory()->create(['name' => 'Basic Academy']), PlanKey::Basic);
    $basic->update(['contact_address' => '5 Market Street, Aba']);

    expect(SchoolContact::for($basic->fresh())->address)->toBe('5 Market Street, Aba');
});
