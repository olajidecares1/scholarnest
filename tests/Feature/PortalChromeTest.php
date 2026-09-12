<?php

use App\Enums\PlanKey;
use App\Models\School;

/**
 * The chrome around a portal sign-in page.
 *
 * These pages exist to show one card. The sticky header bar above it was
 * costing roughly 90px of height for a logo, a wordmark and a link none of
 * these pages use - and on a phone that is the difference between seeing the
 * password field and having to scroll for it.
 *
 * So the portals have no header. The logo sits on its own in the top-left
 * corner, absolutely positioned, taking no height from the card at all.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Greenfield College']), PlanKey::Standard);
});

/**
 * @return array<string, string>
 */
function portalChromePages(School $school): array
{
    return [
        'staff sign-in' => $school->portalLoginUrl('staff'),
        'student sign-in' => $school->portalLoginUrl('student'),
        'parent sign-in' => $school->portalLoginUrl('guardian'),
        'admin sign-in' => $school->portalLoginUrl('web'),
        'portal hub' => route('portal.index', $school),
        'unified sign-in' => route('portal.show'),
    ];
}

describe('the portals have no header bar', function () {
    test('none of them renders one', function () {
        foreach (portalChromePages($this->school) as $label => $url) {
            $html = $this->get($url)->assertOk()->getContent();

            // The bar itself is gone from the markup, not hidden with a class -
            // a hidden header still ships its markup and still runs the Alpine
            // handler attached to the logo inside it.
            expect($html)->not->toContain('<header');
        }
    });

    test('the logo is there instead, in the top-left corner', function () {
        foreach (portalChromePages($this->school) as $label => $url) {
            $html = $this->get($url)->assertOk()->getContent();

            expect($html)->toContain('absolute left-4 top-4');
        }
    });

    test('the corner logo does not swallow clicks meant for the page', function () {
        // It sits in an absolutely positioned box that is wider than the image.
        // Without this the empty space beside the logo would sit on top of the
        // card and eat clicks.
        $html = $this->get($this->school->portalLoginUrl('staff'))->getContent();

        expect($html)->toContain('pointer-events-none absolute')
            ->toContain('pointer-events-auto');
    });
});

describe('the logo is 80px', function () {
    test('on the portals', function () {
        foreach (portalChromePages($this->school) as $label => $url) {
            expect($this->get($url)->getContent())
                ->toContain('h-20 w-20');
        }
    });

    test('on registration, with no wordmark beside it', function () {
        // The mark already carries "AkademicNest". Setting the name twice within
        // 200px reads as a mistake rather than as branding.
        $html = $this->get(route('register'))->assertOk()->getContent();

        expect($html)->toContain('h-20 w-20')
            ->not->toContain('tracking-tight text-[#0F2A5C]">AkademicNest</a>');
    });

    test('80px is a standard class, not an arbitrary one', function () {
        // h-[100px] is an arbitrary value: it only exists in the stylesheet if
        // the build ran, and an unbuilt arbitrary class renders at natural size
        // with no error at all. h-20 is 5rem = 80px and always compiled.
        $css = file_get_contents(
            collect(glob(public_path('build/assets/*.css')))->first() ?: '/dev/null'
        );

        expect($css)->toContain('.h-20{height:calc(var(--spacing) * 20)}');
    });
});

describe('pages that still need a header keep one', function () {
    test('the result checker shows the school its badge', function () {
        // A parent checking their child's result should see their child's
        // school at the top, which is exactly what the header holds.
        $this->get(route('check-result.show', $this->school))
            ->assertOk()
            ->assertSee('<header', false)
            ->assertSee('Greenfield College');
    });
});

describe('the school finder puts its logo above the card', function () {
    // The corner logo is 80px square at 16px from the edge. On a phone the card
    // is full-width and starts about 60px down, so on this page the logo sat on
    // the card's top-left corner. Here it is in the flow instead.
    test('it has no corner logo', function () {
        expect($this->get(route('portal.find.show'))->assertOk()->getContent())
            ->not->toContain('absolute left-4 top-4');
    });

    test('the logo comes before the card, still 80px', function () {
        $html = $this->get(route('portal.find.show'))->getContent();

        $logo = strpos($html, 'mb-6 inline-block');
        $card = strpos($html, 'Find your school</h1>');

        expect($logo)->not->toBeFalse()
            ->and($card)->not->toBeFalse()
            ->and($logo)->toBeLessThan($card)
            ->and($html)->toContain('h-20 w-20');
    });
});
