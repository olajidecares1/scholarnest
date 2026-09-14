<?php

use App\Enums\PlanKey;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolEvent;
use App\Models\SchoolWebsite;

/**
 * Three or fewer sit still; more than three rotate, and either way a visitor
 * can scroll the panel to reach everything in it.
 *
 * A carousel of three items a visitor can already see in full is motion for
 * its own sake, and it hides two of them behind a thirty-second wait for no
 * reason. Past three the panel cannot show them all, and rotating earns its
 * place.
 *
 * The rotation's timing and easing live in resources/js/marquee-list.js now
 * rather than in transition classes on the markup, so the tests that guard the
 * 3s rise and the 30s dwell read that file. Asserting on class strings that no
 * longer exist would pass while proving nothing.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

function seedNews(School $school, int $count): void
{
    foreach (range(1, $count) as $i) {
        NewsPost::factory()->create([
            'school_id' => $school->id,
            'title' => "Story number {$i}",
            'is_published' => true,
            'published_at' => now()->subDays($i),
        ]);
    }
}

function seedEvents(School $school, int $count): void
{
    foreach (range(1, $count) as $i) {
        SchoolEvent::factory()->create([
            'school_id' => $school->id,
            'title' => "Event number {$i}",
            'starts_at' => now()->addDays($i),
            'ends_at' => now()->addDays($i)->addHours(2),
        ]);
    }
}

function marqueeScript(): string
{
    return file_get_contents(base_path('resources/js/marquee-list.js'));
}

describe('three or fewer sit still', function () {
    test('three news stories sit still, and all three are shown', function () {
        seedNews($this->school, 3);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('Story number 1')
            ->toContain('Story number 2')
            ->toContain('Story number 3')
            ->not->toContain('aria-label="Latest news"');
    });

    test('and they use the same card the rotator does', function () {
        // Three used to fall into a three-across grid while four switched to a
        // stacked list, so publishing a fourth story rearranged the panel.
        seedNews($this->school, 3);

        $threeUp = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        seedNews($this->school, 1);

        $rotating = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($threeUp)->not->toContain('sm:grid-cols-2 lg:grid-cols-3')
            ->and($threeUp)->toContain('group flex h-24 overflow-hidden rounded-[10px]')
            ->and($rotating)->toContain('group flex h-24 overflow-hidden rounded-[10px]');
    });

    test('three events sit still', function () {
        seedEvents($this->school, 3);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('Event number 3')
            ->not->toContain('aria-label="Upcoming events"');
    });
});

describe('past three, both panels rotate and both scroll', function () {
    test('four news stories give a scrollable rotating panel', function () {
        seedNews($this->school, 4);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('x-data="marqueeList()"')
            ->toContain('aria-label="Latest news"')
            // A real scrolling box, not a clipped one.
            ->toContain('overflow-y-auto')
            ->toContain('overscroll-contain')
            // Every story is in the page from the start.
            ->toContain('Story number 4');
    });

    test('four events give a scrollable rotating panel', function () {
        seedEvents($this->school, 4);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('x-data="marqueeList()"')
            ->toContain('aria-label="Upcoming events"')
            ->toContain('overflow-y-auto')
            ->toContain('overscroll-contain')
            ->toContain('Event number 4');
    });

    test('a scrolling panel can be reached and scrolled from the keyboard', function () {
        // The events panel holds no links, so without this a keyboard user
        // could not scroll it at all and the removal of "View all" would have
        // put those events out of their reach entirely.
        seedEvents($this->school, 7);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('tabindex="0"')
            ->toContain('role="region"');
    });

    test('the panel scrolls itself but never traps the page behind it', function () {
        seedEvents($this->school, 7);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        // Reaching the last event must not then start scrolling the whole page.
        expect($html)->toContain('overscroll-contain');
    });
});

describe('the "View all" escape hatch is gone from both panels', function () {
    test('the news panel does not link away to a separate news page', function () {
        seedNews($this->school, 12);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->not->toContain(route('public.school-news.index', $this->school).'"');
    });

    test('the events panel does not link away to a separate events page', function () {
        seedEvents($this->school, 12);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->not->toContain(route('public.school-events.index', $this->school).'"');
    });

    test('so every story and every event must be in the panel itself', function () {
        // With nowhere else to send a visitor, "all of them are in the page"
        // stops being a nicety and becomes the only way to read the twelfth.
        seedNews($this->school, 12);
        seedEvents($this->school, 12);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        foreach (range(1, 12) as $i) {
            expect($html)->toContain("Story number {$i}")
                ->toContain("Event number {$i}");
        }
    });
});

describe('news is grouped into sets of three, the same as events', function () {
    test('nine stories give three groups, not nine', function () {
        // The bug this covers: news turned over one story at a time while the
        // events column beside it turned over three, so the two panels moved
        // at different rates and news looked stuck.
        seedNews($this->school, 9);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect(substr_count($html, 'Show stories'))->toBe(3)
            ->and($html)->toContain('Show stories 1 to 3')
            ->toContain('Show stories 4 to 6')
            ->toContain('Show stories 7 to 9');
    });

    test('a final group shorter than three is fine', function () {
        seedNews($this->school, 10);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect(substr_count($html, 'Show stories'))->toBe(4)
            ->and($html)->toContain('Show stories 10 to 10');
    });

    test('both panels end up the same height, so the row sits level', function () {
        seedNews($this->school, 12);
        seedEvents($this->school, 12);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        // Three 96px cards and two 12px gaps make a 312px group, and the track
        // carries 16px more for the gap that keeps one group off the next.
        expect(substr_count($html, 'h-[328px] overflow-y-auto'))->toBe(2)
            ->and(substr_count($html, 'mb-4 min-h-[312px] space-y-3'))->toBe(8);
    });

    test('the grouping is done in PHP, never written into the JavaScript', function () {
        seedNews($this->school, 7);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect(substr_count($html, 'Show stories'))->toBe(3)
            ->and($html)->toContain('x-data="marqueeList()"');
    });
});

describe('events are grouped into sets of three', function () {
    test('more than three events are grouped into sets of three', function () {
        // Nine events is 1-3, 4-6, 7-9: three groups, three dots.
        seedEvents($this->school, 9);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect(substr_count($html, 'Show events'))->toBe(3)
            ->and($html)->toContain('Show events 1 to 3')
            ->toContain('Show events 4 to 6')
            ->toContain('Show events 7 to 9');
    });

    test('a final group shorter than three is fine', function () {
        // Ten events is 3 + 3 + 3 + 1. The last group is simply shorter; the
        // held height stops the next group creeping up into view behind it.
        seedEvents($this->school, 10);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect(substr_count($html, 'Show events'))->toBe(4)
            ->and($html)->toContain('Show events 10 to 10')
            ->and($html)->toContain('min-h-[312px]');
    });

    test('four events give two groups, not four', function () {
        seedEvents($this->school, 4);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect(substr_count($html, 'Show events'))->toBe(2)
            ->and($html)->toContain('Show events 4 to 4');
    });

    test('the grouping is done in PHP, never written into the JavaScript', function () {
        seedEvents($this->school, 7);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        // Three groups in the page, and a script that is told nothing at all
        // about how many there are, it walks whatever it finds.
        expect(substr_count($html, 'Show events'))->toBe(3)
            ->and($html)->toContain('x-data="marqueeList()"')
            ->and(marqueeScript())->toContain('this.$refs.track.children');
    });
});

describe('the animation the grouping was built around is intact', function () {
    test('it still rises over three seconds and is still held thirty', function () {
        expect(marqueeScript())
            ->toContain('dwell = 30000')
            ->toContain('duration = 3000')
            // Eased across the whole distance rather than snapping at the end.
            ->toContain('1 - Math.pow(1 - elapsed, 3)');
    });

    test('it still moves upward, a page at a time', function () {
        // Gliding to a page's own offsetTop rather than by a fixed step is
        // what keeps groups of unequal height landing flush.
        expect(marqueeScript())->toContain('this.glideTo(page.offsetTop)');
    });

    test('the timing is animated by hand, not handed to the browser', function () {
        // scroll-behavior: smooth would let the browser choose the duration,
        // and the whole point of this panel is that it takes three seconds.
        expect(marqueeScript())->not->toContain("behavior: 'smooth'")
            ->and(marqueeScript())->toContain('requestAnimationFrame');
    });
});

describe('it yields to the visitor', function () {
    test('the rotation is skipped for anyone who asked for less motion', function () {
        seedNews($this->school, 4);

        $this->get(route('public.school-website', $this->school))->assertOk();

        // Not a faster rotation, none. Everything stays reachable by scrolling.
        expect(marqueeScript())->toContain("matchMedia('(prefers-reduced-motion: reduce)')");
    });

    test('rotating stops while somebody is reading or scrolling', function () {
        seedEvents($this->school, 7);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('x-on:mouseenter="hold()"')
            ->toContain('x-on:mouseleave="release()"')
            ->toContain('x-on:scroll.passive="onScroll()"')
            ->toContain('x-on:touchstart.passive="hold()"');
    });

    test('the dots move the panel rather than swapping what is visible', function () {
        seedEvents($this->school, 7);

        $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

        expect($html)->toContain('x-on:click="show(0)"')
            ->toContain('x-on:click="show(2)"');
    });
});

/**
 * A stray closing tag in this page once popped a container open and let four
 * segments escape it, and nothing failed, the page still returned 200. It is
 * cheap to count the tags, and this file has just had a large block replaced.
 */
test('the rendered page has balanced div tags', function () {
    seedNews($this->school, 12);
    seedEvents($this->school, 12);

    $html = $this->get(route('public.school-website', $this->school))->assertOk()->getContent();

    expect(preg_match_all('/<div[\s>]/', $html))
        ->toBe(preg_match_all('/<\/div>/', $html));
});
