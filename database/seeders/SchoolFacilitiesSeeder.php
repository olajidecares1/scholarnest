<?php

namespace Database\Seeders;

use App\Models\School;
use App\Models\SchoolFacility;
use Illuminate\Database\Seeder;

/**
 * The facilities a school shows on its public website.
 *
 * The first SIX are the ones from the reference design, in its order, because
 * the panel on the home page shows six and the relation sorts on sort_order,
 * so these are exactly what a visitor sees. The rest fill out the full
 * facilities page behind the "View all" link, which is the one panel on that
 * row that still has one.
 *
 * Every school gets them, on every plan. Facilities is available to Basic as
 * well, see [App\Enums\PlanFeature::requiredPlans], so a Basic school has a
 * facilities page to manage even though it has no public website to show it on.
 *
 * The names are chosen to exercise [App\Support\FacilityIcon] as well as to
 * read well: a laboratory, a computer laboratory and a library all resolve to
 * different icons, and getting that wrong is exactly the bug the icons were
 * added to fix.
 *
 * IDEMPOTENT. Every row is matched on its school and name before it is
 * written, so running this twice does not give a school two libraries.
 */
class SchoolFacilitiesSeeder extends Seeder
{
    /**
     * @var list<array{name: string, category: string, description: string}>
     */
    private const FACILITIES = [
        // The six from the reference, in its order.
        ['Modern Classrooms', 'Academic', 'Airy, well-lit classrooms with seating arranged for group work as well as for teaching from the front.'],
        ['Science Laboratories', 'Academic', 'Separate benches for physics, chemistry and biology, with fume extraction and the equipment for a full practical syllabus.'],
        ['Computer Laboratory', 'Academic', 'A machine for every pupil in the class, with supervised internet access and printing.'],
        ['Library', 'Academic', 'Fiction, reference and past papers across every year group, open through the lunch hour and after school.'],
        ['Sports Facilities', 'Sports', 'A full-size pitch, courts, and a track marked out for the inter-house athletics.'],
        ['School Hall', 'Facilities', 'Where assembly, prize giving and the end-of-term productions happen. Seats the whole school.'],

        // The rest, for the full facilities page.
        ['Music Room', 'Arts', 'Instruments, practice space and somewhere for the choir and the school band to rehearse.'],
        ['Art Studio', 'Arts', 'Space to paint, draw and make, with somewhere for work to dry and be displayed.'],
        ['Sick Bay', 'Welfare', 'Staffed through the school day, with a quiet room for any pupil who is unwell and a place to call home from.'],
        ['Dining Hall', 'Welfare', 'Hot meals prepared on site each day, with the week\'s menu posted on the noticeboard.'],
        ['School Buses', 'Transport', 'Supervised routes covering the main areas of town, morning and afternoon.'],
        ['The Chapel', 'Facilities', 'For the weekly service, Founders\' Day, and quiet at any other time.'],
    ];

    public function run(): void
    {
        // EVERY school, on every plan. Facilities is not a website feature,
        // it is a record of what the school has, and a Basic school has
        // buildings too. The other website seeders in here are right to skip
        // schools with no site; this one would be wrong to.
        $schools = School::all();

        foreach ($schools as $school) {
            foreach (self::FACILITIES as $index => [$name, $category, $description]) {
                SchoolFacility::updateOrCreate(
                    ['school_id' => $school->id, 'name' => $name],
                    [
                        'category' => $category,
                        'description' => $description,
                        'sort_order' => $index + 1,
                    ],
                );
            }
        }

        $this->command?->info(
            "Seeded {$schools->count()} school(s) with ".count(self::FACILITIES).' facilities each.'
        );
    }
}
