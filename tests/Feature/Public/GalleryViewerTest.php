<?php

use App\Enums\PlanKey;
use App\Models\School;
use App\Models\SchoolGalleryImage;
use App\Models\SchoolWebsite;

/**
 * The gallery panel rotates three at a time like the events panel beside it,
 * and a photograph opens in a viewer rather than sending the visitor to a
 * separate page.
 *
 * The viewer is the reason the grouping and the navigation have to be kept
 * apart: the panel shows three, but next and previous walk the whole gallery.
 * Several of these tests exist only to hold that line.
 */
beforeEach(function () {
    $this->school = activateSchool(School::factory()->create(), PlanKey::Standard);
    SchoolWebsite::factory()->create(['school_id' => $this->school->id, 'is_published' => true]);
});

function seedGallery(School $school, int $count): void
{
    foreach (range(1, $count) as $i) {
        SchoolGalleryImage::factory()->create([
            'school_id' => $school->id,
            'image_path' => "gallery/photo-{$i}.jpg",
            'caption' => "Photograph number {$i}",
            'sort_order' => $i,
        ]);
    }
}

function gallery(): string
{
    return test()->get(route('public.school-website', test()->school))->assertOk()->getContent();
}

describe('three or fewer are all shown, and none of them rotate', function () {
    test('one image shows one', function () {
        seedGallery($this->school, 1);

        expect(gallery())->toContain('gallery/photo-1.jpg')
            ->not->toContain('aria-label="Our gallery"');
    });

    test('two images show two', function () {
        seedGallery($this->school, 2);

        expect(gallery())->toContain('gallery/photo-1.jpg')
            ->toContain('gallery/photo-2.jpg')
            ->not->toContain('aria-label="Our gallery"');
    });

    test('three images show three', function () {
        seedGallery($this->school, 3);

        expect(gallery())->toContain('gallery/photo-3.jpg')
            ->not->toContain('aria-label="Our gallery"')
            ->not->toContain('Show photographs');
    });

    test('none at all says so rather than breaking', function () {
        expect(gallery())->toContain('No photographs have been added yet.')
            // Nothing to view, so no viewer.
            ->not->toContain('Gallery image viewer');
    });
});

describe('more than three rotate in groups of three', function () {
    test('nine images give three groups, not nine', function () {
        seedGallery($this->school, 9);

        $html = gallery();

        expect(substr_count($html, 'Show photographs'))->toBe(3)
            ->and($html)->toContain('Show photographs 1 to 3')
            ->toContain('Show photographs 4 to 6')
            ->toContain('Show photographs 7 to 9');
    });

    test('four images give two groups, not four', function () {
        seedGallery($this->school, 4);

        $html = gallery();

        expect(substr_count($html, 'Show photographs'))->toBe(2)
            ->and($html)->toContain('Show photographs 4 to 4');
    });

    test('a final group shorter than three is fine', function () {
        seedGallery($this->school, 10);

        $html = gallery();

        expect(substr_count($html, 'Show photographs'))->toBe(4)
            ->and($html)->toContain('Show photographs 10 to 10')
            // The held height stops the group behind creeping up into view.
            ->and($html)->toContain('min-h-[128px]');
    });

    test('the photographs never touch each other', function () {
        seedGallery($this->school, 9);

        $html = gallery();

        // Side by side, and one row to the next. Without the margin the rows
        // met edge to edge as they slid past and read as one tall picture;
        // the track carries the gap in its height (128 + 16) so a row still
        // fills a page.
        expect($html)->toContain('mb-4 grid min-h-[128px] grid-cols-3 gap-4')
            ->toContain('h-[144px] overflow-y-auto');
    });

    test('and not when they are sitting still either', function () {
        seedGallery($this->school, 3);

        expect(gallery())->toContain('grid grid-cols-3 gap-4');
    });

    test('the count is never hard-coded, at any size', function () {
        // 1..12, and the number of groups is always ceil(n / 3).
        foreach (range(4, 12) as $count) {
            SchoolGalleryImage::where('school_id', $this->school->id)->delete();
            seedGallery($this->school, $count);

            expect(substr_count(gallery(), 'Show photographs'))
                ->toBe((int) ceil($count / 3), "{$count} images");
        }
    });

    test('it uses the same rotation as events and news', function () {
        seedGallery($this->school, 9);

        expect(gallery())->toContain('x-data="marqueeList()"')
            ->toContain('aria-label="Our gallery"');
    });
});

describe('the tile keeps the animation it already had', function () {
    test('the hover zoom is untouched', function () {
        seedGallery($this->school, 9);

        // Same clipped rounded frame, same 500ms ease-out scale as before.
        expect(gallery())->toContain('overflow-hidden rounded-[8px] bg-gray-100')
            ->toContain('object-cover transition-transform duration-500 ease-out hover:scale-110');
    });

    test('and three or fewer get exactly the same tile', function () {
        seedGallery($this->school, 2);

        expect(gallery())->toContain('object-cover transition-transform duration-500 ease-out hover:scale-110');
    });
});

describe('there is no way out of the page', function () {
    test('the "View all" link is gone', function () {
        seedGallery($this->school, 12);

        expect(gallery())->not->toContain(route('public.school-gallery.index', $this->school));
    });

    test('a tile opens the viewer instead of navigating', function () {
        seedGallery($this->school, 9);

        $html = gallery();

        expect($html)->toContain('x-on:click="openAt(0, $event)"')
            // A button, not a link, nothing to follow, nothing to reload.
            ->toContain('<button')
            ->toContain('Gallery image viewer');
    });

    test('so every photograph must be reachable from the panel itself', function () {
        seedGallery($this->school, 12);

        $html = gallery();

        foreach (range(1, 12) as $i) {
            expect($html)->toContain("gallery/photo-{$i}.jpg");
        }
    });
});

describe('the viewer walks the whole gallery, not the visible three', function () {
    test('every image is handed to the viewer, whichever group it is in', function () {
        seedGallery($this->school, 12);

        $html = gallery();

        // The payload is one flat list of twelve. If the viewer were given
        // only the current group, opening image 3 could never reach image 12.
        expect($html)->toContain('x-data="galleryViewer(')
            ->toContain('Photograph number 12');
    });

    test('tiles carry their position in the full list, not in their group', function () {
        seedGallery($this->school, 9);

        $html = gallery();

        // The first tile of the third group is image 7, not image 1.
        expect($html)->toContain('x-on:click="openAt(6, $event)"')
            ->toContain('x-on:click="openAt(8, $event)"');
    });

    test('the viewer stops at the ends rather than asking for what is not there', function () {
        expect(file_get_contents(base_path('resources/js/gallery-viewer.js')))
            ->toContain('return this.index < this.images.length - 1;')
            ->toContain('return this.index > 0;');
    });

    test('and the buttons say so', function () {
        seedGallery($this->school, 9);

        expect(gallery())->toContain('x-bind:disabled="! hasPrevious"')
            ->toContain('x-bind:disabled="! hasNext"');
    });
});

describe('the viewer itself', function () {
    beforeEach(fn () => seedGallery($this->school, 9));

    test('it fills about 70% of a desktop window and adapts below that', function () {
        expect(gallery())->toContain('h-[70vh] w-[92vw]')
            ->toContain('sm:w-[70vw]');
    });

    test('the photograph is never cropped or stretched to fit', function () {
        expect(gallery())->toContain('object-contain');
    });

    test('the backdrop puts the photograph in front of the page', function () {
        expect(gallery())->toContain('bg-black/80');
    });

    test('it opens and closes with an animation', function () {
        expect(gallery())->toContain('transition-opacity ease-out duration-300')
            ->toContain('opacity-0 scale-95');
    });

    test('previous, next and close are Font Awesome icons', function () {
        expect(gallery())->toContain('fa-solid fa-chevron-left')
            ->toContain('fa-solid fa-chevron-right')
            ->toContain('fa-solid fa-xmark');
    });

    test('it closes on the backdrop, on the X, and on Escape', function () {
        expect(gallery())->toContain('x-on:keydown.escape.window="close()"')
            ->toContain('bg-black/80" x-on:click="close()"')
            ->toContain('aria-label="Close viewer"');
    });

    test('closing puts the visitor back where they were', function () {
        // Hiding the body scrollbar takes its width out of the layout and
        // shunts the page sideways; both that and the scroll position are
        // put back.
        expect(file_get_contents(base_path('resources/js/gallery-viewer.js')))
            ->toContain('window.scrollTo(0, this.scrollY)')
            ->toContain("document.body.style.paddingRight = ''")
            // And focus returns to the tile they opened, not the top of the page.
            ->toContain('this.opener?.focus()');
    });

    test('it is announced as a dialog', function () {
        expect(gallery())->toContain('role="dialog"')
            ->toContain('aria-modal="true"');
    });

    test('the controls are large enough to tap', function () {
        // 44px square, the usual floor for a touch target, on all three of
        // them. Matched against the viewer's own button styling rather than
        // "h-11 w-11" alone, which other parts of the page also use.
        // Matched against the viewer's own control class rather than
        // "h-11 w-11" alone, which other parts of the page also use. The
        // resting fill moved into that class when the controls gained a brand
        // colour on hover.
        expect(preg_match_all('#h-11 w-11[^"]*edn-viewer-control#', gallery()))->toBe(3);
    });
});
