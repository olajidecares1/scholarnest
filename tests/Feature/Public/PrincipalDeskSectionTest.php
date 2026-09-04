<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\User;
use Illuminate\Http\UploadedFile;
use Illuminate\Support\Facades\Storage;

/**
 * The Principal's Desk, the Quote of the Week, and the school's own statements.
 *
 * These were removed from the public site while their columns stayed in the
 * database - so a school could have a principal's message stored and no way to
 * see it or edit it. They are back, below Events, Facilities and the Gallery.
 */
beforeEach(function () {
    Storage::fake('public');

    $this->school = activateSchool(School::factory()->create(['name' => 'Greenfield College']), PlanKey::Standard);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

    $this->website = $this->school->website()->create(['hero_title' => 'Welcome', 'is_published' => true]);
});

test('the section is absent entirely when a school has filled none of it in', function () {
    // A "From the Principal's Desk" heading over an empty box is worse than
    // no section.
    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertDontSee('From the Principal');
});

test('the message, the name and the title all appear', function () {
    $this->website->update([
        'principal_message' => 'Welcome to Greenfield, where every child is known by name.',
        'principal_name' => 'Mr. Gregory A. Eze',
        'principal_title' => 'Principal & Founder',
    ]);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('From the Principal')
        ->assertSee('every child is known by name')
        ->assertSee('Mr. Gregory A. Eze')
        ->assertSee('Principal &amp; Founder', false);
});

test('the title falls back to Principal when none is given', function () {
    $this->website->update([
        'principal_message' => 'A word of welcome.',
        'principal_name' => 'Mr. Gregory A. Eze',
        'principal_title' => null,
    ]);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('Principal');
});

test('the quote of the week appears with its author', function () {
    $this->website->update([
        'quote_text' => 'Education is the most powerful weapon.',
        'quote_author' => 'Nelson Mandela',
        'quote_author_role' => 'Former President of South Africa',
    ]);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('Quote of the Week')
        ->assertSee('Education is the most powerful weapon')
        ->assertSee('Nelson Mandela')
        ->assertSee('Former President of South Africa');
});

test('mission, vision and values are shown on the home page', function () {
    // Editable all along, under the About Us tab, and shown nowhere.
    $this->website->update([
        'mission' => 'To teach every child well.',
        'vision' => 'To be the school families choose first.',
        'values' => 'Excellence, Integrity, Discipline.',
    ]);

    $this->get(route('public.school-website', $this->school))
        ->assertOk()
        ->assertSee('Our Mission')
        ->assertSee('To teach every child well')
        ->assertSee('Our Vision')
        ->assertSee('Our Values')
        ->assertSee('Excellence, Integrity, Discipline');
});

test('it sits below the gallery and above contact', function () {
    $this->website->update(['principal_message' => 'A word of welcome.']);

    $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

    // Position, not merely presence: the school speaking for itself reads
    // best after a visitor has seen what the school actually does.
    expect(strpos($html, 'id="gallery"'))->toBeLessThan(strpos($html, 'id="principal"'))
        ->and(strpos($html, 'id="principal"'))->toBeLessThan(strpos($html, 'id="contact"'));
});

describe('the School Admin can edit all of it', function () {
    test('the fields are on the About Us tab', function () {
        $this->actingAs($this->admin)
            ->get(route('website.index'))
            ->assertOk()
            ->assertSee('Principal&rsquo;s Desk', false)
            ->assertSee('name="principal_message"', false)
            ->assertSee('name="principal_photo"', false)
            ->assertSee('Quote of the Week')
            ->assertSee('name="quote_text"', false);
    });

    test('saving them writes to the website', function () {
        $this->actingAs($this->admin)
            ->put(route('website.update-about-fields'), [
                'principal_name' => 'Mr. Gregory A. Eze',
                'principal_title' => 'Principal',
                'principal_message' => 'Welcome to Greenfield.',
                'quote_text' => 'Education changes everything.',
                'quote_author' => 'Nelson Mandela',
                'principal_photo' => UploadedFile::fake()->image('principal.jpg'),
            ])
            ->assertSessionHasNoErrors();

        $website = $this->website->fresh();

        expect($website->principal_name)->toBe('Mr. Gregory A. Eze')
            ->and($website->principal_message)->toBe('Welcome to Greenfield.')
            ->and($website->quote_text)->toBe('Education changes everything.')
            ->and($website->principal_photo_path)->not->toBeNull();

        Storage::disk('public')->assertExists($website->principal_photo_path);
    });

    test('editing the wording does not clear the photograph', function () {
        $this->website->update(['principal_photo_path' => 'website/kept.jpg']);

        $this->actingAs($this->admin)
            ->put(route('website.update-about-fields'), ['principal_message' => 'Reworded.'])
            ->assertSessionHasNoErrors();

        expect($this->website->fresh()->principal_photo_path)->toBe('website/kept.jpg');
    });
});
