<?php

namespace Database\Seeders;

use App\Enums\EventAudience;
use App\Models\NewsPost;
use App\Models\School;
use App\Models\SchoolEvent;
use Illuminate\Database\Seeder;
use Illuminate\Support\Carbon;

/**
 * Latest News and Upcoming Events for a school's public website.
 *
 * Twelve of each - enough that both panels are past the three-item threshold
 * and rotate, and enough for the full News and Events pages to look like a
 * school that has been running a while.
 *
 * IDEMPOTENT. Every row is matched on its school and title before it is
 * written, so running this twice does not give a school twenty-four of
 * everything.
 *
 * Dates are relative to when it runs, not fixed. "Open Day, 12 September 2026"
 * seeded once is a past event by the following term, and an Upcoming Events
 * panel whose events have all happened is worse than an empty one.
 */
class SchoolNewsAndEventsSeeder extends Seeder
{
    /**
     * @var list<array{title: string, category: string, excerpt: string, body: string, days_ago: int}>
     */
    private const NEWS = [
        ['Valedictory Service', 'School News', 'A memorable valedictory service for our graduating students.', 'Our graduating class was celebrated at this year\'s valedictory service, attended by parents, staff and members of the wider school community. Awards were presented for academic achievement, leadership and service.', 6],
        ['Excellent WAEC Results', 'Academic', 'Our students excel again in the WAEC examinations.', 'This year\'s results continue a strong record of achievement, with the great majority of our candidates earning credits in English Language and Mathematics. We congratulate our students and thank the teachers and parents who supported them.', 13],
        ['Debate Team Victory', 'Achievement', 'Our debate team wins the regional championship competition.', 'Our senior team took first place at the regional championship, seeing off strong competition across four rounds. The judges praised the clarity of their arguments on education policy and civic responsibility.', 21],
        ['New Science Laboratory Opens', 'Facilities', 'A fully equipped laboratory for our senior science students.', 'The new laboratory gives our senior students proper bench space, fume extraction and equipment for practical chemistry and physics, replacing arrangements that had served us for many years.', 28],
        ['Inter-House Sports Champions', 'Sports', 'Blue House takes the trophy after a closely fought day.', 'A full day of athletics ended with Blue House edging ahead in the final relay. Thank you to every parent who came out to cheer, and to the staff who organised the day.', 34],
        ['Founders\' Day Celebration', 'School News', 'Marking another year since the school opened its doors.', 'Pupils, staff, alumni and parents gathered to mark Founders\' Day with a service, a short history of the school, and a reception for former pupils.', 41],
        ['Mathematics Olympiad Finalists', 'Academic', 'Three of our pupils reach the national finals.', 'Three senior pupils placed in the top bracket of the regional round and go forward to the national finals. They have been preparing with the mathematics department since the start of term.', 49],
        ['Community Clean-Up Exercise', 'Community', 'Our senior students give a day to the neighbourhood.', 'Senior pupils spent a Saturday clearing and tidying the streets around the school, working alongside residents and the local association.', 57],
        ['Cultural Day', 'School News', 'A day of dress, food, music and language from across Nigeria.', 'Pupils came in traditional dress representing their heritage, and the day closed with performances from each year group. Parents contributed food from their own kitchens.', 65],
        ['Teachers\' Professional Development Week', 'Staff', 'A week of training for our teaching staff.', 'Our teachers spent the week on classroom assessment, differentiated instruction and the use of technology in lessons, led by facilitators from within and outside the school.', 73],
        ['New Library Books Donated', 'Facilities', 'Over four hundred titles added to the school library.', 'A donation from our parents\' association has added more than four hundred titles across fiction, reference and the sciences. The library is now open through the lunch hour.', 82],
        ['Prize Giving Day', 'Achievement', 'Recognising academic excellence and good character.', 'Prizes were awarded for academic achievement, most improved pupil, and for the qualities of character the school works hardest to encourage.', 91],
    ];

    /**
     * @var list<array{title: string, description: string, location: string, in_days: int, start: string, end: string}>
     */
    private const EVENTS = [
        ['Open Day', 'A chance for prospective parents and pupils to visit, meet the teachers and see the classrooms in use.', 'Main Campus', 6, '10:00', '14:00'],
        ['Parent-Teacher Conference', 'Meet your child\'s teachers to discuss progress this term.', 'School Hall', 13, '09:00', '15:00'],
        ['Annual Inter-House Sports', 'Our yearly inter-house athletics competition. Parents are warmly invited.', 'Sports Complex', 20, '08:00', '16:00'],
        ['Science and Technology Fair', 'Pupils present projects they have worked on through the term.', 'Science Block', 28, '10:00', '15:00'],
        ['Career Day', 'Professionals from a range of fields speak to our senior students about their work.', 'School Hall', 35, '09:00', '14:00'],
        ['Mid-Term Break Begins', 'School closes for the mid-term break and reopens the following week.', 'Main Campus', 42, '13:00', '14:00'],
        ['Cultural Day', 'Traditional dress, food, music and performances from every year group.', 'School Grounds', 52, '10:00', '16:00'],
        ['Inter-School Debate', 'Our team hosts three neighbouring schools for the termly debate.', 'School Hall', 60, '11:00', '15:00'],
        ['Founders\' Day Service', 'A service marking another year since the school opened.', 'School Chapel', 68, '09:00', '11:00'],
        ['Prize Giving Day', 'Recognising academic achievement and good character across every year.', 'School Hall', 78, '10:00', '13:00'],
        ['End of Term Examinations Begin', 'Examinations begin for all year groups. The timetable is on the notice board.', 'All Classrooms', 88, '08:00', '13:00'],
        ['Speech and Valedictory Service', 'Celebrating our graduating class and the year behind us.', 'School Hall', 99, '10:00', '14:00'],
    ];

    public function run(): void
    {
        // Only schools that actually have a public website. A Basic school has
        // no site for any of this to appear on.
        $schools = School::query()->whereHas('website')->get();

        foreach ($schools as $school) {
            $this->seedNews($school);
            $this->seedEvents($school);
        }

        $this->command?->info("Seeded {$schools->count()} school(s) with ".count(self::NEWS).' news posts and '.count(self::EVENTS).' events each.');
    }

    private function seedNews(School $school): void
    {
        foreach (self::NEWS as [$title, $category, $excerpt, $body, $daysAgo]) {
            NewsPost::updateOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                [
                    'category' => $category,
                    'excerpt' => $excerpt,
                    'body' => $body,
                    'is_published' => true,
                    'published_at' => now()->subDays($daysAgo),
                ],
            );
        }
    }

    private function seedEvents(School $school): void
    {
        foreach (self::EVENTS as [$title, $description, $location, $inDays, $start, $end]) {
            $day = Carbon::today()->addDays($inDays);

            SchoolEvent::updateOrCreate(
                ['school_id' => $school->id, 'title' => $title],
                [
                    'description' => $description,
                    'location' => $location,
                    'audience' => EventAudience::Everyone,
                    'is_all_day' => false,
                    'starts_at' => $day->copy()->setTimeFromTimeString($start),
                    'ends_at' => $day->copy()->setTimeFromTimeString($end),
                ],
            );
        }
    }
}
