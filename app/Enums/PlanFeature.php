<?php

namespace App\Enums;

/**
 * The features a plan buys, and what to say when a school has not bought one.
 *
 * Every plan restriction in the application is described here once. Before
 * this, each gate was its own middleware class holding its own copy of the
 * rule and its own sentence of prose, which is how the project ended up with
 * some features gated, some not, and a different message for each, a school
 * could reach the Events manager on a plan that has no public website to put
 * events on.
 *
 * Adding a feature means adding a case and putting `plan_feature:<value>` on
 * its routes. Nothing else has to know about it.
 */
enum PlanFeature: string
{
    case Cbt = 'cbt';
    case CbtPractice = 'cbt-practice';
    case Guardians = 'guardians';
    case Events = 'events';
    case News = 'news';
    case Careers = 'careers';
    case Testimonials = 'testimonials';
    case Facilities = 'facilities';
    case CoCurricular = 'co-curricular';
    case Website = 'website';
    case IdCards = 'id-cards';
    case CustomDomain = 'custom-domain';

    // The case values are the route prefixes, which is what lets a nav item,
    // a module card and the middleware all reach the same answer. Note
    // 'assignments' is homework set for students, 'teacher-assignments',
    // which is putting teachers in front of classes, is a different thing on
    // every plan and does not match this prefix.
    case Assignments = 'assignments';
    case Library = 'library';
    case Transport = 'transport';
    case Hostel = 'hostels';
    case Finance = 'finance';
    case Diary = 'diary';
    case Timetable = 'timetable';

    /**
     * The feature a route belongs to, or null if it is on every plan.
     *
     * Derived from the route name rather than kept in a second list: the case
     * values ARE the route prefixes, so a nav item, a module card and the
     * middleware on the route all reach the same answer without anyone having
     * to keep three lists in step. That is what let the interface offer links
     * to features the backend would refuse.
     */
    public static function forRoute(?string $routeName): ?self
    {
        $prefix = str($routeName ?? '')->before('.')->toString();

        return match ($prefix) {
            // Oversight of tests and the tests themselves are one purchase.
            'cbt-tests' => self::Cbt,
            default => self::tryFrom($prefix),
        };
    }

    /**
     * The plans that include this feature.
     *
     * @return list<PlanKey>
     */
    public function requiredPlans(): array
    {
        return match ($this) {
            self::CustomDomain => [PlanKey::Exclusive],

            // Every plan, Basic included. A school's buildings are a plain fact
            // about the school rather than a premium extra, and a Basic school
            // still needs somewhere to record them, its facilities show on its
            // ID cards and printed material even with no public website to put
            // them on.
            self::Facilities => [PlanKey::Basic, PlanKey::Standard, PlanKey::Exclusive],

            default => [PlanKey::Standard, PlanKey::Exclusive],
        };
    }

    /**
     * What the school calls this feature. Used as the page's subject, so it
     * reads as the thing they just clicked rather than a route name.
     */
    public function label(): string
    {
        return match ($this) {
            self::Cbt => 'CBT Tests',
            self::CbtPractice => 'CBT Practice',
            self::Guardians => 'Parent & Guardian Accounts',
            self::Events => 'Events',
            self::News => 'News',
            self::Careers => 'Job Portal & Recruitment',
            self::Testimonials => 'Testimonials',
            self::Facilities => 'Facilities',
            self::CoCurricular => 'Co-curricular Activities',
            self::Website => 'School Website',
            self::IdCards => 'ID Card Management',
            self::CustomDomain => 'Custom Domain',
            self::Assignments => 'Assignments',
            self::Library => 'Library',
            self::Transport => 'Transport',
            self::Hostel => 'Hostel',
            self::Finance => 'Finance',
            self::Diary => 'Teacher Diary',
            self::Timetable => 'Timetable',
        };
    }

    /**
     * Why it is not available, in the school's terms.
     *
     * Each of these says what the feature would do FOR them, because a school
     * being turned away deserves to know what it is being turned away from,
     * "not included in your plan" on its own tells them nothing.
     */
    public function blurb(): string
    {
        return match ($this) {
            self::Cbt => 'Set computer-based tests, publish them to your classes, and have every script marked the moment a student submits.',
            self::CbtPractice => 'Give your students past questions from WAEC, JAMB and NECO to practise against, with instant scoring.',
            self::Guardians => 'Give parents and guardians their own accounts to follow attendance, results and fees for every child they are linked to.',
            self::Events => 'Publish your school calendar (open days, sports, prize-givings) to your public website so families can plan around it.',
            self::News => 'Share announcements and stories from your school on your public website, with photos and full articles.',
            self::Careers => 'Publish vacancies on your own Job Portal, share them on WhatsApp and social media, and manage applicants, CVs and interviews in one place.',
            self::Testimonials => 'Show what parents and alumni say about your school on your public website.',
            self::Facilities => 'Showcase your classrooms, laboratories, library and grounds with photos on your public website.',
            self::CoCurricular => 'Run clubs, sports and societies alongside the timetable, and show them on your public website.',
            self::Website => 'A complete public website for your school on your own subdomain, with a hero slider, gallery, contact page and SEO built in.',
            self::IdCards => 'Design and print student and staff ID cards from your own records, with verifiable QR codes.',
            self::CustomDomain => 'Run your website on your school\'s own domain name, with automatic SSL and DNS guidance.',
            self::Assignments => 'Set homework for a class, collect it online, and mark and return it without a stack of exercise books.',
            self::Library => 'Catalogue your books and track every loan and return, so you know what is out and who has it.',
            self::Transport => 'Keep your vehicles, routes and stops on record, and see which students travel on each one.',
            self::Hostel => 'Manage your hostels room by room, with bed allocations and a live view of who is where.',
            self::Finance => 'Raise invoices, record payments and see what each family still owes, all against your own student records.',
            self::Diary => 'Have every teacher log the topic they taught each week, subject by subject, and read it back by class, term or session.',
            self::Timetable => 'Build the week\'s lesson timetable class by class, and give every teacher and pupil their own copy of it.',
        };
    }

    /**
     * Font Awesome icon, matching the one on the module card this feature
     * would have been reached through.
     */
    public function icon(): string
    {
        return match ($this) {
            self::Cbt => 'fa-file-circle-check',
            self::CbtPractice => 'fa-laptop-code',
            self::Guardians => 'fa-user-shield',
            self::Events => 'fa-calendar-days',
            self::News => 'fa-newspaper',
            self::Careers => 'fa-briefcase',
            self::Testimonials => 'fa-quote-left',
            self::Facilities => 'fa-building-columns',
            self::CoCurricular => 'fa-medal',
            self::Website => 'fa-globe',
            self::IdCards => 'fa-id-card',
            self::CustomDomain => 'fa-link',
            self::Assignments => 'fa-list-check',
            self::Library => 'fa-book',
            self::Transport => 'fa-bus',
            self::Hostel => 'fa-building',
            self::Finance => 'fa-sack-dollar',
            self::Diary => 'fa-folder',
            self::Timetable => 'fa-clock',
        };
    }

    /**
     * "the Standard or Exclusive plan", or "the Exclusive plan", written out
     * so the page names the plans rather than a tier number.
     */
    public function requiredPlanLabel(): string
    {
        $names = array_map(fn (PlanKey $plan) => $plan->label(), $this->requiredPlans());

        if (count($names) === 1) {
            return "the {$names[0]}";
        }

        $last = array_pop($names);

        return 'the '.implode(', ', $names)." or {$last}";
    }
}
