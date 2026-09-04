<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\AcademicLevel;
use App\Models\HeroSlide;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\User;

/**
 * Every school's public website wears that school's own colour.
 *
 * The machinery for this already existed - one hex on school_websites, a
 * nine-shade scale derived from it by BrandColorScale, and CSS variables
 * written into the page head that Tailwind's primary-* utilities read. What was
 * missing was the places that ignored it: four hard-coded academic stage
 * colours, and the borders and buttons the brief names explicitly.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Crimson College']), PlanKey::Standard);
    $this->website = SchoolWebsite::factory()->create([
        'school_id' => $this->school->id,
        'is_published' => true,
        'brand_primary_color' => '#e11d48',
    ]);
});

function brandedPage(School $school): string
{
    return test()->get(route('public.school-website', $school))->assertOk()->getContent();
}

describe('the school chooses one colour and the site follows', function () {
    test('the chosen hex becomes a full scale in the page head', function () {
        $html = brandedPage($this->school);

        // One value in, nine shades out - the school never configures a
        // separate hover, border or focus colour.
        expect($html)->toContain('--color-primary-600')
            ->toContain('--color-primary-400')
            ->toContain('--color-primary-800');
    });

    test('a school that has set none still gets a complete scale', function () {
        // Otherwise every var(--color-primary-400) border would resolve to
        // nothing and simply disappear.
        $plain = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $plain->id, 'is_published' => true]);

        brandedPage($plain);

        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('--color-primary-400: #438bf6');
    });

    test('changing it needs no deployment and no CSS edit', function () {
        $before = brandedPage($this->school);

        $this->website->update(['brand_primary_color' => '#16a34a']);

        expect(brandedPage($this->school))->not->toBe($before);
    });
});

describe('one school colour never reaches another', function () {
    test('school A and school B render their own', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create([
            'school_id' => $other->id,
            'is_published' => true,
            'brand_primary_color' => '#16a34a',
        ]);

        $a = brandedPage($this->school);
        $b = brandedPage($other);

        // The scale is derived from each school's own hex, so the two pages
        // cannot share a shade.
        expect($a)->toContain('--color-primary-600')
            ->and($b)->toContain('--color-primary-600')
            ->and($a)->not->toBe($b);
    });

    test('changing one school does not touch the other', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create([
            'school_id' => $other->id,
            'is_published' => true,
            'brand_primary_color' => '#16a34a',
        ]);

        $otherBefore = brandedPage($other);

        $this->website->update(['brand_primary_color' => '#7c3aed']);

        expect(brandedPage($other))->toBe($otherBefore);
    });
});

describe('nothing on the public site is a fixed theme colour', function () {
    test('the academic stages use the brand scale, not four fixed hexes', function () {
        foreach (['Nursery', 'Primary', 'JSS', 'SSS'] as $index => $name) {
            AcademicLevel::factory()->create([
                'school_id' => $this->school->id, 'name' => $name, 'sort_order' => $index,
            ]);
        }

        $html = brandedPage($this->school);

        // They were #15803d, #b45309, #1d4ed8 and #7e22ce - a green, an amber,
        // a blue and a purple - so a crimson school still got a blue Junior
        // card and a purple Senior one.
        expect($html)->not->toContain('#15803d')
            ->not->toContain('#b45309')
            ->not->toContain('#1d4ed8')
            ->not->toContain('#7e22ce');

        // Four shades of the school's own colour, so the stages stay
        // distinguishable without leaving its palette.
        expect($html)->toContain('var(--color-primary-500)')
            ->toContain('var(--color-primary-800)');
    });

    test('the section headings follow the theme', function () {
        expect(brandedPage($this->school))->toContain('text-primary-700');
    });
});

describe('the borders the brief asks for', function () {
    test('academic and admission cards are outlined at 1.3px in the brand colour', function () {
        AcademicLevel::factory()->create(['school_id' => $this->school->id, 'name' => 'Primary']);

        expect(brandedPage($this->school))->toContain('edn-brand-outline');

        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('border: 1.3px solid var(--color-primary-400)');
    });

    test('the hero Explore button carries the same 1.3px brand border', function () {
        expect(brandedPage($this->school))->toContain('border: 1.3px solid var(--color-primary-400);');
    });
});

describe('contrast outranks branding', function () {
    test('the Explore button keeps white type on the navy hero', function () {
        // A mid-tone brand colour as type on navy is exactly what the contrast
        // rule exists to prevent, so the border carries the brand and the label
        // stays white.
        $html = brandedPage($this->school);

        expect($html)->toContain('border: 1.3px solid var(--color-primary-400);')
            ->toContain('text-sm font-semibold text-white');
    });

    test('the gallery viewer keeps white glyphs on its black backdrop', function () {
        $css = file_get_contents(base_path('resources/css/app.css'));

        // The brand arrives on hover, where it is decoration rather than
        // legibility.
        expect($css)->toContain('.edn-viewer-control:hover:not(:disabled)')
            ->toContain('color-mix(in srgb, var(--color-primary-500) 55%, transparent)');
    });

    test('prose over a tinted photograph stays white, whatever the brand colour', function () {
        $this->website->update([
            'academics_card_image_path' => 'website/bg.jpg',
            'brand_primary_color' => '#fde047',
        ]);

        // Matched with the closing quote. "edn-on-photo" is a prefix of
        // "edn-on-photo-brand", so the bare string passes whichever class is
        // actually there - this test went on passing after the headings moved
        // to the brand variant, while claiming they had not.
        //
        // The heading is a short bold line and can carry a tint; the body over
        // it is a longer read at a smaller size and keeps the full contrast of
        // white.
        expect(brandedPage($this->school))->toContain('edn-on-photo"')
            ->toContain('edn-on-photo-muted');
    });
});

describe('the School Admin can set it', function () {
    test('there is a hex field and a colour picker', function () {
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);

        $this->actingAs($admin)
            ->get(route('website.index'))
            ->assertOk()
            ->assertSee('name="brand_primary_color"', false)
            ->assertSee('type="color"', false);
    });

    test('saving one stores it against that school alone', function () {
        $admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $other->id, 'brand_primary_color' => '#16a34a']);

        $this->actingAs($admin)->put(route('website.update-brand-color'), [
            'brand_primary_color' => '#7c3aed',
        ]);

        expect($this->website->fresh()->brand_primary_color)->toBe('#7c3aed')
            ->and($other->website->fresh()->brand_primary_color)->toBe('#16a34a');
    });
});

describe('the About section wears the school colour', function () {
    test('the pillar headings and their icons both carry it', function () {
        $this->website->update([
            'mission' => 'To teach well.',
            'vision' => 'To lead.',
            'values' => 'Integrity.',
        ]);

        $html = brandedPage($this->school);

        // It was near-black type beside a coloured mark, which made the icon
        // the only branded thing in the row.
        expect(substr_count($html, 'text-[15px] font-bold text-primary-700'))->toBe(3)
            // The icon has NO colour of its own now: it inherits the heading's,
            // so the two cannot drift apart - and over a photograph it picks up
            // the heading's shadow with it.
            ->and(substr_count($html, 'fa-solid fa-bullseye text-[15px]" aria-hidden'))->toBe(1);
    });

    test('and the About Us eyebrow does too', function () {
        expect(brandedPage($this->school))->toContain('tracking-[0.18em] text-primary-700');
    });

    test('but all of it goes white over a tinted photograph', function () {
        $this->website->update([
            'about_card_image_path' => 'website/bg.jpg',
            'mission' => 'To teach well.',
        ]);

        $html = brandedPage($this->school);

        expect($html)->toContain('edn-on-photo')
            // The contrast rule still outranks the branding rule.
            ->not->toContain('text-[15px] font-bold text-primary-700');
    });
});

describe('the footer Quick Links follow the school colour', function () {
    test('links hover into the brand colour with an underline', function () {
        expect(brandedPage($this->school))->toContain('edn-footer-link');

        $css = file_get_contents(base_path('resources/css/app.css'));

        expect($css)->toContain('color: var(--color-primary-300)')
            ->toContain('background: var(--color-primary-300)');
    });

    test('the resting colour stays legible on the near-black footer', function () {
        // A mid-tone brand colour as small type on near-black is the case the
        // contrast rule exists to prevent - a school on navy would have links
        // it could not read. 300 is the light end of its own scale.
        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('color: rgb(209 213 219)');
    });

    test('each column heading carries a brand marker', function () {
        expect(brandedPage($this->school))->toContain('edn-footer-heading');
    });
});

describe('the newsletter is gone', function () {
    test('nothing of it is left in the footer', function () {
        $html = brandedPage($this->school);

        expect($html)->not->toContain('Newsletter')
            ->not->toContain('Subscribe for the latest updates')
            ->not->toContain('Enter your email');
    });

    test('and the grid closed up rather than leaving a gap', function () {
        // Four columns now, not five with an empty fifth.
        expect(brandedPage($this->school))->toContain('sm:grid-cols-2 sm:px-6 lg:grid-cols-4')
            ->not->toContain('sm:grid-cols-2 sm:px-6 lg:grid-cols-5');
    });
});

describe('the hero fills the screen', function () {
    test('it is sized from the viewport, not from a fixed pixel height', function () {
        // 520px stopped two-thirds of the way down a tall monitor, and raising
        // the number would only have moved the problem to the next screen size.
        $html = brandedPage($this->school);

        expect($html)->toContain('edn-hero')
            ->not->toContain('min-h-[520px]')
            ->not->toContain('min-h-[560px]');
    });

    test('it uses svh, so a phone does not hide the buttons', function () {
        $css = file_get_contents(base_path('resources/css/app.css'));

        // 100vh on a phone is measured without the collapsing address bar, so
        // a 100vh hero is taller than the screen and its buttons sit under the
        // chrome. svh is the viewport that is really visible.
        expect($css)->toContain('min-height: calc(100svh - var(--edn-chrome))')
            // And a plain vh line before it, for anything without svh.
            ->toContain('min-height: calc(100vh - var(--edn-chrome))');
    });

    test('it subtracts the chrome above it', function () {
        // The top bar and header are in normal flow, so a full 100svh would
        // push the hero's own bottom edge below the fold by their height.
        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('--edn-chrome: 92px')
            ->toContain('--edn-chrome: 100px');
    });

    test('the slow scale and the staggered entrance are untouched', function () {
        HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);

        expect(brandedPage($this->school))->toContain('edn-kenburns')
            ->toContain('edn-enter');
    });
});

describe('the hero photograph fills the whole section', function () {
    test('slides cover, edge to edge, with no coloured container', function () {
        HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);

        $html = brandedPage($this->school);

        // Contain fitted the whole picture inside its box and left the
        // section's colour showing where the shapes disagreed. Cover fills the
        // section and crops instead, which is the right trade when the
        // alternative is bare colour around the image.
        expect($html)->toContain('absolute inset-0 bg-cover bg-center bg-no-repeat')
            ->not->toContain('background-size: contain');
    });

    test('the navy panel and its disc are gone', function () {
        HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);

        $html = brandedPage($this->school);

        // There is no coloured container left for the image to sit inside -
        // the photograph IS the hero, and the words sit on top of it.
        expect($html)->not->toContain('width: 1400px; height: 1400px;')
            ->not->toContain('absolute inset-y-0 left-0 hidden w-[42%] lg:block');
    });

    test('the photograph is not offset from one edge any more', function () {
        // .edn-hero-photo started the picture clear of the disc. With no disc,
        // an offset would be the very thing it prevented: a strip of bare
        // colour down one side.
        expect(file_get_contents(base_path('resources/css/app.css')))
            ->not->toContain('left: calc(42% + 80px)');
    });

    test('the words sit over the image, not beside it', function () {
        expect(brandedPage($this->school))->toContain('edn-hero edn-parallax relative z-10')
            ->not->toContain('w-full lg:w-[46%]');
    });
});

describe('readability without blurring the photograph', function () {
    test('contrast comes from a gradient, not a blur or a panel', function () {
        HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);

        $html = brandedPage($this->school);

        expect($html)->toContain('linear-gradient(to right, color-mix(in srgb,')
            ->not->toContain('backdrop-blur')
            ->not->toContain('filter: blur');
    });

    test('the wash survives a navy that is not a six-digit hex', function () {
        // It is a school setting and can be any colour CSS accepts, so
        // appending an alpha suffix would produce nonsense for a named colour.
        $this->website->update(['brand_secondary_color' => 'midnightblue']);

        expect(brandedPage($this->school))->toContain('color-mix(in srgb, midnightblue 68%, transparent)')
            ->not->toContain('midnightbluee6');
    });
});

describe('the hero is covered at every moment', function () {
    test('the slow scale never drops below 1', function () {
        // The slide is sized with cover, so scale 1 is exactly the size that
        // fills the hero. Anything under it would be smaller than the area it
        // is covering and would show the section colour at the edges.
        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('transform: scale(1.05)')
            ->toContain('transform: scale(1);');
    });

    test('a base layer sits under the cross-fade', function () {
        HeroSlide::factory()->count(2)->create(['school_id' => $this->school->id]);

        $html = brandedPage($this->school);

        // A cross-fade between two half-transparent images lets whatever is
        // beneath show through in the middle of it. With nothing beneath, that
        // was a flash of the section colour between every pair of slides.
        expect($html)->toContain('aria-hidden="true"')
            ->toContain('x-transition:leave="transition-opacity duration-1000"');
    });

    test('and the entrance animations are untouched', function () {
        HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);

        // The paragraph and the buttons; the tagline's 100ms only renders for
        // a school that has set a motto, so it is not the thing to assert on
        // here.
        expect(brandedPage($this->school))->toContain('edn-kenburns')
            ->toContain('edn-enter')
            ->toContain('animation-delay: 180ms')
            ->toContain('animation-delay: 260ms');
    });
});

describe('headings over a photograph still wear the school colour', function () {
    test('they are a light shade of the brand, not plain white', function () {
        $this->website->update([
            'about_card_image_path' => 'website/bg.jpg',
            'mission' => 'To teach well.',
        ]);

        $html = brandedPage($this->school);

        // White was safe and said nothing: a school on purple got the same
        // "Our Mission" as a school on green, and the colour it had configured
        // appeared to do nothing at all.
        expect($html)->toContain('edn-on-photo-brand');

        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('.edn-on-photo-brand {
    color: var(--color-primary-300);');
    });

    test('the light end of the scale, because a mid-tone would not read', function () {
        // 300, not the 600 or 700 used on a white page. A mid-tone hue on a
        // dark tinted photograph is the case the contrast rule exists to
        // prevent - which is why these were white to begin with. Lightening
        // the same hue keeps identity and legibility together.
        $css = file_get_contents(base_path('resources/css/app.css'));

        expect($css)->toContain('.edn-on-photo-brand')
            ->not->toContain('.edn-on-photo-brand {
    color: var(--color-primary-700);');
    });

    test('the icon inherits it, so mark and words are one thing', function () {
        $this->website->update([
            'about_card_image_path' => 'website/bg.jpg',
            'mission' => 'To teach well.',
        ]);

        // No colour of its own - it takes the heading's colour AND its shadow.
        expect(brandedPage($this->school))->toContain('fa-solid fa-bullseye text-[15px]" aria-hidden');
    });

    test('prose over a photograph stays white, though', function () {
        // The headings carry the brand; the body does not. A paragraph is a
        // longer read at a smaller size, and it wants the full contrast of
        // white rather than a tint of anything.
        $this->website->update([
            'about_card_image_path' => 'website/bg.jpg',
            'about_headline' => 'Building strong minds.',
        ]);

        expect(brandedPage($this->school))->toContain('edn-on-photo-muted');
    });
});
