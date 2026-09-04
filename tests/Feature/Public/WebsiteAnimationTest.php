<?php

use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Models\AcademicLevel;
use App\Models\HeroSlide;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Models\SchoolWebsite;
use App\Models\Staff;

/**
 * The website's animation system.
 *
 * Most of what makes an animation good cannot be asserted in a feature test -
 * whether it feels smooth is a matter for eyes. What CAN be pinned is the part
 * that silently breaks: that the markup carries the hooks, that the stagger is
 * computed rather than guessed, that nothing animates a layout property, and
 * that a visitor who asked for less motion still gets every word of the page.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(['name' => 'Animated Academy']), PlanKey::Standard);
    $this->website = SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

function animatedPage(): string
{
    return test()->get(route('public.school-website', test()->school))->assertOk()->getContent();
}

function stylesheet(): string
{
    return file_get_contents(base_path('resources/css/app.css'));
}

function revealScript(): string
{
    return file_get_contents(base_path('resources/js/scroll-reveal.js'));
}

describe('sections reveal as they are scrolled to', function () {
    test('every section heading carries the reveal hook', function () {
        $html = animatedPage();

        // SEVEN at this size - Academics, Admissions, News, Events,
        // Facilities, Gallery, Contact. About's is a size larger now that the
        // section is one centred column rather than a narrow half.
        expect(substr_count($html, 'edn-reveal text-[11.5px] font-bold uppercase'))->toBe(7)
            ->and(substr_count($html, 'edn-reveal text-[12.5px] font-bold uppercase'))->toBe(1);
    });

    test('a heading and its accent rule arrive as one layered reveal', function () {
        expect(animatedPage())->toContain('edn-accent');

        // The rule waits on top of whatever delay the heading was given, which
        // is what makes the pair read as one event rather than two.
        expect(stylesheet())->toContain('transition-delay: calc(var(--edn-delay, 0ms) + 250ms)');
    });

    test('it reveals once and never again', function () {
        // A section that re-animated every time the visitor nudged the scroll
        // wheel would be unbearable, and a count that restarted on the way
        // back up would look broken.
        expect(revealScript())->toContain('observer.unobserve(el)');
    });
});

describe('the stagger is computed, not guessed', function () {
    test('academic cards are 100ms apart', function () {
        foreach (['Creche', 'Nursery', 'Primary', 'Secondary'] as $index => $name) {
            AcademicLevel::factory()->create([
                'school_id' => $this->school->id, 'name' => $name, 'sort_order' => $index,
            ]);
        }

        $html = animatedPage();

        expect($html)->toContain('--edn-delay: 0ms;')
            ->toContain('--edn-delay: 100ms;')
            ->toContain('--edn-delay: 200ms;')
            ->toContain('--edn-delay: 300ms;');
    });

    test('admission cards are 120ms apart', function () {
        $html = animatedPage();

        expect($html)->toContain('--edn-delay: 120ms;')
            ->toContain('--edn-delay: 240ms;');
    });

    test('the delay is a custom property, not a class per value', function () {
        // A class would mean inventing edn-delay-100, -200, -300 and hoping
        // nobody ever needs 120 - which the admission cards do.
        expect(stylesheet())->toContain('transition-delay: var(--edn-delay, 0ms)');
    });
});

describe('the about section is one centred column', function () {
    test('it no longer arrives from two sides, because it no longer has two', function () {
        // It used to be prose on the left and a large framed photograph on the
        // right, each sliding in to meet the other. The photograph is gone, so
        // there is nothing to slide against and the block simply rises.
        $this->website->update(['about_image_path' => 'website/about.jpg']);

        $html = animatedPage();

        expect($html)->not->toContain('edn-reveal edn-reveal-left')
            ->not->toContain('edn-reveal edn-reveal-right');
    });

    test('the text is centred and larger than it was', function () {
        $this->website->update(['about_headline' => 'Building strong minds.']);

        $html = animatedPage();

        // A measure on the prose: centred text run the full width of a desktop
        // stops being readable.
        expect($html)->toContain('relative mx-auto max-w-4xl text-center')
            ->toContain('text-[32px] font-extrabold')
            ->toContain('text-[19px] font-bold leading-snug');
    });

    test('and it gained the accent rule the other sections have', function () {
        expect(animatedPage())->toContain('edn-accent mx-auto mt-4 block h-[3px] w-14');
    });
});

describe('the statistics count up', function () {
    test('a numeric stat counts, and keeps whatever trails it', function () {
        $this->website->update(['stats' => [
            ['label' => 'Students', 'value' => '1,200+'],
            ['label' => 'Teachers', 'value' => '48'],
        ]]);

        $html = animatedPage();

        expect($html)->toContain('data-count-to="1200"')
            ->toContain('data-count-suffix="+"')
            ->toContain('data-count-to="48"');
    });

    test('a stat that is not a number is left alone', function () {
        $this->website->update(['stats' => [['label' => 'Rating', 'value' => 'Grade A']]]);

        $html = animatedPage();

        expect($html)->toContain('Grade A')
            ->not->toContain('data-count-to="0"');
    });

    test('the real figure is in the markup, not a zero', function () {
        // So a visitor with no JavaScript, or with reduced motion, reads the
        // number rather than a nought that never moves.
        $this->website->update(['stats' => [['label' => 'Students', 'value' => '1,200']]]);

        expect(animatedPage())->toContain('>1,200</span>');
    });

    test('and the script blanks it before counting', function () {
        // Otherwise the true value shows and then snaps to zero the moment the
        // bar is scrolled to, which reads as a bug rather than an animation.
        expect(revealScript())->toContain("el.textContent = formatCount(0, el.dataset.countSuffix ?? '')");
    });
});

describe('navigation', function () {
    test('desktop links have an underline that draws in', function () {
        expect(stylesheet())->toContain('.edn-nav-link::after')
            ->toContain('transform: scaleX(0)');
    });

    test('the mobile drawer staggers its items', function () {
        expect(animatedPage())->toContain('edn-drawer-item')
            ->toContain('edn-drawer-open');
    });

    test('the drawer stagger is capped so a long menu still closes promptly', function () {
        // Nine links at 55ms would be nearly half a second of waiting on the
        // last one.
        expect(animatedPage())->not->toContain('--edn-delay: 495ms;');
    });

    test('the navbar scroll handler is throttled and passive', function () {
        $layout = file_get_contents(base_path('resources/views/components/public-site-layout.blade.php'));

        // A scroll event fires far more often than the screen refreshes.
        expect($layout)->toContain('requestAnimationFrame')
            ->toContain('{ passive: true }');
    });
});

describe('performance', function () {
    test('only opacity and transform are transitioned', function () {
        $css = stylesheet();

        // Both are composited by the GPU. Animating width, height, top or
        // margin instead would reflow the document on every frame.
        expect($css)->toContain('transition:
        opacity 0.7s cubic-bezier(0.16, 1, 0.3, 1),
        transform 0.7s cubic-bezier(0.16, 1, 0.3, 1)');
    });

    test('will-change is released once an element has arrived', function () {
        // Leaving it on a hundred settled elements keeps them all in their own
        // compositor layers for nothing.
        expect(stylesheet())->toContain('will-change: auto');
    });

    test('there is no continuous animation loop', function () {
        $script = revealScript();

        // A CALL, not the word - the file explains in a comment why
        // requestAnimationFrame is used rather than setInterval, and matching
        // the bare word failed on that sentence.
        expect($script)->not->toContain('setInterval(')
            // The only rAF loops are the count-up, which animates a number
            // rather than a style and stops when it arrives, and the scroll
            // throttle, which does nothing unless the visitor scrolls.
            ->and(substr_count($script, 'requestAnimationFrame('))->toBeLessThanOrEqual(4);
    });

    test('parallax is REDUCED on a small screen, not removed', function () {
        // It used to be cleared outright below 1024px, which left a phone with
        // the static page the effect exists to prevent. A phone has less
        // horsepower and a shorter viewport, so the same travel reads as more
        // movement - the answer is a gentler version, not none.
        expect(revealScript())->toContain('strength = window.innerWidth >= 1024 ? 1 : 0.45')
            ->toContain('?? 0.12) * strength')
            ->toContain('?? 60) * strength');

        // Reduced motion is a different question from a small screen, and is
        // still answered by removing the movement entirely.
        expect(stylesheet())->toContain('.edn-parallax {
        transform: none !important;');
    });

    test('layer positions are measured once, not on every frame', function () {
        // offsetTop is a layout-reading property: asking for it inside a
        // scroll handler forces the browser to flush layout before it can
        // answer, on every frame, for every layer.
        $script = revealScript();

        expect($script)->toContain('box.top + window.scrollY + box.height / 2')
            ->toContain('(viewportMiddle - positions[index]) * rate')
            // NOT offsetTop, which is measured from the nearest positioned
            // ancestor - and every one of these layers is absolutely
            // positioned inside its own section, so it was 0, never the
            // distance down the page, and every layer sat pinned at its cap.
            //
            // The broken EXPRESSION, not the bare identifier: the file
            // explains the fix in a comment that names it.
            ->not->toContain('map((layer) => layer.offsetTop)')
            ->not->toContain('(y - positions[index])')
            // Re-measured when the viewport changes shape, because a resize or
            // a rotation can move a layer without any scroll event firing.
            ->toContain("window.addEventListener(\n        'resize',");
    });

    test('the background lags and the content leads, which is what makes depth', function () {
        // Neither movement is large; what the eye reads is the difference
        // between them.
        $this->website->update(['academics_card_image_path' => 'website/bg.jpg']);

        $html = animatedPage();

        expect($html)->toContain('data-parallax-rate="0.06"')
            ->toContain('data-parallax-rate="-0.04"');
    });

    test('content only drifts where there is a photograph behind it', function () {
        // Depth is a relationship between two layers. With a flat background
        // there is no second layer, and content sliding on its own over plain
        // colour reads as a rendering fault.
        expect(animatedPage())->not->toContain('data-parallax-rate="-0.04"');
    });
});

describe('reduced motion', function () {
    test('everything is visible and still', function () {
        $css = stylesheet();

        // The content is the point; the animation was only ever the manner of
        // its arrival.
        expect($css)->toContain('.edn-reveal,
    .edn-reveal-left,
    .edn-reveal-right,
    .edn-reveal-scale {
        opacity: 1;
        transform: none;
        transition: none;
    }');
    });

    test('no observer or scroll handler is created at all', function () {
        // Nothing to turn off later, because nothing was started.
        expect(revealScript())->toContain('if (wantsLessMotion()) {');
    });

    test('a browser without IntersectionObserver still shows the page', function () {
        // The alternative is a page of permanently invisible content.
        expect(revealScript())->toContain("if (! ('IntersectionObserver' in window))");
    });
});

describe('the existing animations are untouched', function () {
    test('the news and events rotation still runs on its own timings', function () {
        expect(revealScript())->not->toContain('marquee');

        expect(file_get_contents(base_path('resources/js/marquee-list.js')))
            ->toContain('dwell = 30000')
            ->toContain('duration = 3000');
    });

    test('the gallery tile keeps its hover zoom', function () {
        // Needs a photograph to hang the tile on.
        SchoolGalleryImage::factory()->create([
            'school_id' => $this->school->id, 'image_path' => 'gallery/one.jpg',
        ]);

        expect(animatedPage())->toContain('transition-transform duration-500 ease-out hover:scale-110');
    });

    test('the hero keeps its staggered entrance and slow scale', function () {
        // The slow scale is on the slide image, so there has to be one.
        HeroSlide::factory()->create([
            'school_id' => $this->school->id, 'sort_order' => 0,
        ]);

        expect(animatedPage())->toContain('edn-enter')
            ->toContain('edn-kenburns');
    });
});

test('the page still has balanced div tags', function () {
    // This work added wrapper elements around several cards, which is exactly
    // the kind of edit that leaves one behind.
    $html = animatedPage();

    expect(preg_match_all('/<div[\s>]/', $html))
        ->toBe(preg_match_all('/<\/div>/', $html));
});

// -----------------------------------------------------------------------------
// Sharp, not frosted; translucent, not opaque; and images that drift
// -----------------------------------------------------------------------------

describe('the site is sharp rather than blurred', function () {
    test('the navbar no longer frosts what scrolls behind it', function () {
        // backdrop-blur on a sticky header re-filters the region behind it on
        // every frame, and softens the top of every photograph and card as it
        // passes. A solid bar is sharper at any opacity and costs nothing.
        // The rendered page, not the source: the layout explains in a comment
        // why backdrop-blur was removed, and matching the bare word failed on
        // that sentence.
        expect(animatedPage())->not->toContain('backdrop-blur');
    });

    test('nothing on the public page blurs an image', function () {
        expect(animatedPage())->not->toContain('backdrop-blur')
            ->not->toContain('blur-sm')
            ->not->toContain('filter: blur');
    });

    test('the translucent cards use a flat alpha, not a backdrop filter', function () {
        $css = stylesheet();

        expect($css)->toContain('.edn-translucent-card')
            ->toContain('background-color: rgb(255 255 255 / 82%)');

        // The whole point: the photograph behind stays crisp.
        expect(substr($css, strpos($css, '.edn-translucent-card'), 300))
            ->not->toContain('backdrop-filter');
    });
});

describe('images drift with the scroll', function () {
    test('the hero photograph has its own drifting layer', function () {
        HeroSlide::factory()->create(['school_id' => $this->school->id, 'sort_order' => 0]);

        expect(animatedPage())->toContain('edn-parallax');
    });

    test('the section backgrounds drift on their own layers', function () {
        // The About photograph that used to drift inside its frame is gone
        // with the card that held it. The section BACKGROUNDS still drift, and
        // for the same reason they always did - a background cannot be
        // transformed, only the element carrying it can.
        $this->website->update([
            'about_card_image_path' => 'website/about-bg.jpg',
            'academics_card_image_path' => 'website/academics-bg.jpg',
        ]);

        expect(substr_count(animatedPage(), 'edn-parallax'))->toBeGreaterThanOrEqual(2);
    });

    test('a drifting layer is always given room to move', function () {
        // Travel with no headroom uncovers a bare edge, which is worse than no
        // parallax at all. The section layers sit 50px beyond top and bottom
        // and cap their travel at 40.
        $this->website->update(['about_card_image_path' => 'website/about-bg.jpg']);

        expect(animatedPage())->toContain('top: -50px; bottom: -50px;')
            ->toContain('data-parallax-max="40"');
    });

    test('each layer caps its own travel', function () {
        // The hero's layer is inset 70px; the About frame has far less room.
        // One shared cap would show an edge on the tighter of the two.
        expect(revealScript())->toContain('layer.dataset.parallaxMax ?? 60');
    });

    test('it is off below the desktop breakpoint, in both the CSS and the JS', function () {
        expect(stylesheet())->toContain('.edn-parallax {
        transform: none !important;')
            ->and(revealScript())->toContain('window.innerWidth >= 1024');
    });
});

test('the teachers, principal and quote block is gone from the template', function () {
    // Removed entirely, not just unstyled: it was three tinted panels holding
    // a teacher marquee, a principal's message and a quote, and none of it is
    // on the page any more.
    Staff::factory()->count(2)->create([
        'school_id' => $this->school->id,
        'role' => StaffRole::Teacher,
        'is_active' => true,
    ]);

    expect(animatedPage())->not->toContain('Meet Our Amazing Teachers')
        ->not->toContain("From the Principal's Desk")
        ->not->toContain('Experienced. Dedicated. Inspiring.');
});

test('and the staff query behind it went with it', function () {
    // Left in the controller it would have been a query run on every visit to
    // every school's front page for a variable no view reads.
    expect(file_get_contents(base_path('app/Http/Controllers/PublicSchoolWebsiteController.php')))
        ->not->toContain("'teachers' => \$school->staff()");
});
