<?php

use App\Enums\PlanKey;
use App\Enums\UserRole;
use App\Models\School;
use App\Models\SchoolWebsite;
use App\Models\User;
use App\Support\WebsiteTypography;

/**
 * A school chooses its typeface and how heavy its body text is, and its public
 * website is set in that.
 *
 * The family is never a value a school typed: it is written into a CSS
 * declaration on every visitor's page, so it is checked against the curated
 * list before it is stored AND again before it is rendered.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Typeface Academy']), PlanKey::Standard);
    $this->website = SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
    $this->admin = User::factory()->create(['role' => UserRole::SchoolAdmin, 'school_id' => $this->school->id]);
});

function typePage(School $school): string
{
    return test()->get(route('public.school-website', $school))->assertOk()->getContent();
}

describe('the school picks it, the website wears it', function () {
    test('the chosen family reaches the page and is fetched once', function () {
        $this->website->update(['font_family' => 'Poppins', 'font_weight' => 600]);

        $html = typePage($this->school);

        expect($html)->toContain("--edn-font-family: 'Poppins'")
            ->toContain('--edn-font-weight: 600')
            // Asked for from Google along with whatever the blocks use, in one
            // request rather than two.
            ->toContain('fonts.googleapis.com');
    });

    test('the font is applied to the body, so it reaches the whole page', function () {
        $this->website->update(['font_family' => 'Lato', 'font_weight' => 700]);

        expect(typePage($this->school))->toContain('font-family: var(--edn-font-family)')
            ->toContain('font-weight: var(--edn-font-weight)');
    });

    test('the body carries no font-sans class to override it', function () {
        // font-sans is a CLASS and beats the body element rule, so the chosen
        // typeface would have been set and then silently overridden on the very
        // element it was written for.
        expect(typePage($this->school))->not->toContain('bg-white font-sans text-gray-900');
    });

    test('a school that has chosen nothing gets the default', function () {
        expect(WebsiteTypography::familyFor($this->website))->toBe('Inter')
            ->and(WebsiteTypography::weightFor($this->website))->toBe(400);
    });
});

describe('the hierarchy survives the base weight', function () {
    test('headings keep their own weight classes', function () {
        $this->website->update(['font_weight' => 300 + 100]);

        $html = typePage($this->school);

        // The base weight is set on body alone. Every heading carries its own
        // font-bold or font-extrabold utility, and a class beats an inherited
        // value, so the page keeps its shape instead of flattening to one
        // weight.
        expect($html)->toContain('font-extrabold')
            ->toContain('font-bold');
    });
});

describe('one school never wears another school font', function () {
    test('two schools render their own', function () {
        $this->website->update(['font_family' => 'Poppins']);

        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create([
            'school_id' => $other->id, 'is_published' => true, 'font_family' => 'Merriweather',
        ]);

        expect(typePage($this->school))->toContain("'Poppins'")
            ->and(typePage($other))->toContain("'Merriweather'")
            ->and(typePage($other))->not->toContain("--edn-font-family: 'Poppins'");
    });
});

describe('nothing arbitrary reaches the stylesheet', function () {
    test('an unknown family is refused on the way in', function () {
        $this->actingAs($this->admin)
            // A name that is genuinely in neither list. This used to say
            // "Comic Sans MS", which stopped being invalid the moment the
            // installed fonts were added, a test that asserts a refusal has
            // to name something the list will never contain.
            ->put(route('website.update-typography'), [
                'font_family' => 'Wingdings Ultra Expanded',
                'font_weight' => 400,
            ])
            ->assertSessionHasErrors('font_family');

        expect($this->website->fresh()->font_family)->toBeNull();
    });

    test('and an unknown one already in the column is ignored on the way out', function () {
        // Belt and braces: a family removed from the curated list later, or
        // written by anything that bypassed the form, must not render.
        $this->website->forceFill(['font_family' => 'Definitely Not A Font'])->save();

        expect(WebsiteTypography::familyFor($this->website->fresh()))->toBe('Inter');

        expect(typePage($this->school))->not->toContain('Definitely Not A Font');
    });

    test('a stylesheet URL cannot be smuggled in as a family name', function () {
        $this->actingAs($this->admin)
            ->put(route('website.update-typography'), [
                'font_family' => "Inter'; } body { display: none; } .x{",
                'font_weight' => 400,
            ])
            ->assertSessionHasErrors('font_family');
    });
});

describe('the weight has to be one the family really ships', function () {
    test('a weight the family does not publish is refused', function () {
        // PT Sans ships 400 and 700 only. Asking Google for 500 returns a
        // stylesheet without it and the browser fakes the difference.
        $this->actingAs($this->admin)
            ->put(route('website.update-typography'), ['font_family' => 'PT Sans', 'font_weight' => 500])
            ->assertSessionHasErrors('font_weight');
    });

    test('a weight it does publish is accepted', function () {
        $this->actingAs($this->admin)
            ->put(route('website.update-typography'), ['font_family' => 'PT Sans', 'font_weight' => 700])
            ->assertSessionHasNoErrors();

        expect($this->website->fresh()->font_weight)->toBe(700);
    });

    test('a stored weight the family lost is snapped to the nearest real one', function () {
        // A school picks Montserrat 800, then switches family. The stored
        // number can outlive the family that had it.
        $this->website->forceFill(['font_family' => 'PT Sans', 'font_weight' => 800])->save();

        expect(WebsiteTypography::weightFor($this->website->fresh()))->toBe(700);
    });

    test('every family offers the whole of its published range to the page', function () {
        // Not just the base weight: the design sets headings in 600, 700 and
        // 800 with utility classes, and loading only the base would leave every
        // one of those synthesised.
        $this->website->update(['font_family' => 'Poppins', 'font_weight' => 400]);

        expect(WebsiteTypography::googleFontsFor($this->website->fresh()))
            ->toBe(['Poppins' => [400, 500, 600, 700, 800]]);
    });
});

describe('the admin controls', function () {
    test('there is a family select, a range slider and a live preview', function () {
        $html = $this->actingAs($this->admin)->get(route('website.index'))->assertOk()->getContent();

        expect($html)->toContain('name="font_family"')
            ->toContain('type="range"')
            ->toContain('name="font_weight"')
            ->toContain('Live Preview')
            // The number beside the slider, so it is not a mystery control.
            ->toContain('Font Weight: <span x-text="weight">');
    });

    test('the slider wears the school brand colour', function () {
        expect($this->actingAs($this->admin)->get(route('website.index'))->getContent())
            ->toContain('edn-brand-range');

        expect(file_get_contents(base_path('resources/css/app.css')))
            ->toContain('background: var(--color-primary-600)');
    });

    test('every typography control explains itself', function () {
        $html = $this->actingAs($this->admin)->get(route('website.index'))->assertOk()->getContent();

        expect($html)->toContain("Select the font style you want your school's public website to use")
            ->toContain('Use the slider to make the general website text lighter or bolder')
            ->toContain('Choose the primary color used throughout');
    });

    test('saving stores it against that school alone', function () {
        $other = activateSchool(School::factory()->create(), PlanKey::Standard);
        SchoolWebsite::factory()->create(['school_id' => $other->id, 'font_family' => 'Lato']);

        $this->actingAs($this->admin)
            ->put(route('website.update-typography'), ['font_family' => 'Rubik', 'font_weight' => 500])
            ->assertSessionHasNoErrors();

        expect($this->website->fresh()->font_family)->toBe('Rubik')
            ->and($other->website->fresh()->font_family)->toBe('Lato');
    });
});

describe('installed fonts sit beside the web fonts', function () {
    test('Algerian is offered, and is never asked of Google', function () {
        // It is a Microsoft display face shipped with Office, not a Google
        // font, there is no URL to request it from. Listing it as a web font
        // would have produced a request for a family Google does not have and
        // a page that rendered in the fallback on every machine, including the
        // ones that own the typeface.
        expect(WebsiteTypography::families())->toHaveKey('Algerian')
            ->and(WebsiteTypography::isSystemFamily('Algerian'))->toBeTrue();

        $this->website->update(['font_family' => 'Algerian', 'font_weight' => 400]);

        expect(WebsiteTypography::googleFontsFor($this->website->fresh()))->toBe([]);
    });

    test('it carries its own fallbacks, not the generic sans stack', function () {
        // Georgia should fall back to a serif and Courier New to a monospace.
        // One shared sans-serif stack would answer every system face with the
        // same wrong thing.
        $this->website->update(['font_family' => 'Algerian']);
        expect(WebsiteTypography::stackFor($this->website->fresh()))->toContain('fantasy');

        $this->website->update(['font_family' => 'Georgia']);
        expect(WebsiteTypography::stackFor($this->website->fresh()))->toEndWith('serif');

        $this->website->update(['font_family' => 'Courier New']);
        expect(WebsiteTypography::stackFor($this->website->fresh()))->toContain('monospace');
    });

    test('a school can actually save Algerian', function () {
        $this->actingAs($this->admin)
            ->put(route('website.update-typography'), ['font_family' => 'Algerian', 'font_weight' => 400])
            ->assertSessionHasNoErrors();

        expect($this->website->fresh()->font_family)->toBe('Algerian');

        expect(typePage($this->school))->toContain("--edn-font-family: 'Algerian'");
    });

    test('Algerian offers one weight, because it has one cut', function () {
        // Offering a bold would only ask the browser to smear the outlines,
        // which on a face this heavy looks like a fault.
        expect(WebsiteTypography::weightsFor('Algerian'))->toBe([400]);

        $this->actingAs($this->admin)
            ->put(route('website.update-typography'), ['font_family' => 'Algerian', 'font_weight' => 700])
            ->assertSessionHasErrors('font_weight');
    });

    test('the picker separates the two kinds so a school knows what it is choosing', function () {
        $html = $this->actingAs($this->admin)->get(route('website.index'))->assertOk()->getContent();

        expect($html)->toContain('Web fonts (look the same for every visitor)')
            ->toContain('Installed fonts (only for visitors who have them)')
            ->toContain('>Algerian<');
    });
});

describe('the list is a good deal longer now', function () {
    test('there are plenty to choose from, of every kind', function () {
        $families = WebsiteTypography::families();

        expect(count($families))->toBeGreaterThan(80)
            // Sans, serif, display, script and monospace, so a school is not
            // choosing between thirty versions of the same idea.
            ->and($families)->toHaveKeys([
                'Inter', 'Poppins',            // sans
                'EB Garamond', 'Lora',         // serif
                'Bebas Neue', 'Abril Fatface', // display
                'Dancing Script', 'Pacifico',  // script
                'JetBrains Mono',              // monospace
                'Algerian', 'Georgia',         // installed
            ]);
    });

    test('every web font still declares real weights', function () {
        // The whole reason the weights are written down: Google omits any it
        // does not publish and the browser fakes the difference.
        // Collected and asserted once, rather than passing a message as a
        // second argument to toContain, which Pest reads as ANOTHER value to
        // look for, so the message itself became the thing being searched for.
        $missing = [];

        foreach (config('website_fonts.fonts') as $family => $weights) {
            if ($weights === [] || ! in_array(400, $weights, true)) {
                $missing[] = $family;
            }
        }

        expect($missing)->toBe([]);
    });
});
