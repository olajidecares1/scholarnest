<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\SchoolWebsite;
use Database\Seeders\SchoolWebsiteContentSeeder;
use Illuminate\Support\Facades\Storage;

beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Vincent Martins College']), PlanKey::Standard);
    $this->website = SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

function page(): string
{
    return test()->get(route('public.school-website', test()->school))->assertOk()->getContent();
}

describe('the band above the footer', function () {
    test('it is laid out, not positioned blocks in a fixed box', function () {
        // The bug: the band drew website-builder blocks, which are placed by
        // coordinate, into a 90px box, so the headline, the subline and the
        // button landed on top of one another.
        $html = page();

        expect($html)->not->toContain('height="90px"')
            ->toContain('lg:flex-row lg:items-center lg:justify-between');
    });

    test('it is always there, with words, even for a school that wrote none', function () {
        $html = page();

        expect($html)->toContain('Give Your Child the Foundation for a Successful Future')
            ->toContain('Admissions are open for the coming academic session.')
            ->toContain('Apply for Admission')
            ->toContain('Contact Us');
    });

    test('a school that wrote its own is not overwritten by the default', function () {
        $this->website->update([
            'cta_title' => 'Join the Class of 2030',
            'cta_subtitle' => 'Places for September are filling quickly.',
            'cta_text' => 'Start an Application',
        ]);

        $html = page();

        expect($html)->toContain('Join the Class of 2030')
            ->toContain('Places for September are filling quickly.')
            ->toContain('Start an Application')
            ->not->toContain('Give Your Child the Foundation');
    });

    test('the second button goes to the form on this page', function () {
        // The same rule as every other admission button on the site: scroll to
        // the contact form rather than navigate to a separate page.
        expect(page())->toContain('#contact');
    });

    test('it carries the graduation cap from the reference', function () {
        expect(page())->toContain('fa-solid fa-graduation-cap');
    });
});

describe('the About segment', function () {
    test('it shows the mission, vision and values once they are written', function () {
        $this->website->update([
            'about_headline' => 'Building strong minds, character and future leaders.',
            'mission' => 'To provide quality education.',
            'vision' => 'To be a leading institution.',
            'values' => 'Excellence, Integrity, Discipline.',
        ]);

        $html = page();

        expect($html)->toContain('About Vincent Martins College')
            ->toContain('Building strong minds, character and future leaders.')
            ->toContain('Our Mission')
            ->toContain('Our Vision')
            ->toContain('Our Values')
            ->toContain('Learn More');
    });

    test('it does not leave half the section empty when there is no photograph', function () {
        // A fixed two-column grid put the text in the left half and nothing at
        // all in the right, for every school that had not uploaded one.
        expect($this->website->aboutImageUrl())->toBeNull();

        expect(page())->not->toContain('items-center gap-10 lg:grid-cols-2 lg:gap-14');
    });

    test('and does use two columns once there is one', function () {
        Storage::fake('public');
        Storage::disk('public')->put('website/about.jpg', 'x');
        $this->website->update(['about_image_path' => 'website/about.jpg']);

        expect(page())->toContain('lg:grid-cols-2');
    });
});

describe('seeding fills both segments', function () {
    test('it writes the About and call-to-action content', function () {
        Storage::fake('public');

        $this->seed(SchoolWebsiteContentSeeder::class);

        $website = $this->website->fresh();

        expect($website->about_headline)->not->toBeEmpty()
            ->and($website->mission)->not->toBeEmpty()
            ->and($website->vision)->not->toBeEmpty()
            ->and($website->values)->not->toBeEmpty()
            ->and($website->cta_title)->not->toBeEmpty()
            ->and($website->cta_subtitle)->toContain('academic session are now open')
            // And a plate to hold the space until a real photograph arrives.
            ->and($website->about_image_path)->not->toBeEmpty();

        Storage::disk('public')->assertExists($website->about_image_path);
    });

    test('it never overwrites what a school has already written', function () {
        Storage::fake('public');

        $this->website->update([
            'mission' => 'Our own mission, thank you.',
            'cta_title' => 'Our own headline.',
        ]);

        $this->seed(SchoolWebsiteContentSeeder::class);

        $website = $this->website->fresh();

        expect($website->mission)->toBe('Our own mission, thank you.')
            ->and($website->cta_title)->toBe('Our own headline.')
            // While still filling the blanks around them.
            ->and($website->vision)->not->toBeEmpty();
    });

    test('running it twice changes nothing the second time', function () {
        Storage::fake('public');

        $this->seed(SchoolWebsiteContentSeeder::class);
        $first = $this->website->fresh()->only(['mission', 'vision', 'values', 'cta_title', 'about_image_path']);

        $this->seed(SchoolWebsiteContentSeeder::class);

        expect($this->website->fresh()->only(['mission', 'vision', 'values', 'cta_title', 'about_image_path']))->toBe($first);
    });
});
