<?php

namespace App\Support;

/**
 * The icon and the one-line description for each place in the application.
 *
 * Keyed by ROUTE, not by label. Five sidebars use different words for the same
 * destination - "Test/Exam Score" in one portal, "Report Cards" in another -
 * and an icon chosen five times ends up different in each.
 *
 * The descriptions exist because the sidebar item is a card now: an icon, the
 * feature's name, and a line saying what it is for. They are deliberately
 * short - a description that wraps to three lines makes the sidebar a wall of
 * text and defeats the point of having icons at all.
 */
class SidebarMeta
{
    /**
     * Used when a route has no entry. A plain circle rather than a guess: a
     * wrong icon is worse than a neutral one, and a test fails if any rendered
     * item falls back to it.
     */
    public const FALLBACK_ICON = 'fa-solid fa-circle-dot';

    /**
     * @var array<string, array{0: string, 1: string}>
     */
    private const META = [
        // --- Everywhere ------------------------------------------------
        'dashboard' => ['fa-solid fa-gauge', 'Overview of your school'],
        'staff.dashboard' => ['fa-solid fa-gauge', 'Your teaching overview'],
        'student.dashboard' => ['fa-solid fa-gauge', 'Your school at a glance'],
        'guardian.dashboard' => ['fa-solid fa-gauge', "Your child's overview"],
        'super-admin.dashboard' => ['fa-solid fa-gauge', 'Platform overview'],

        // --- People ----------------------------------------------------
        'students.index' => ['fa-solid fa-user-graduate', 'Admissions and pupil records'],
        'guardians.index' => ['fa-solid fa-users', 'Parent accounts and links'],
        'staff.index' => ['fa-solid fa-chalkboard-user', 'Teachers and other staff'],
        'teacher-assignments.index' => ['fa-solid fa-user-check', 'Who teaches what, and where'],
        'super-admin.document-templates.index' => ['fa-solid fa-file-invoice', 'Report card and ID card templates'],
        'super-admin.users.index' => ['fa-solid fa-users', 'Platform accounts'],
        'super-admin.roles.index' => ['fa-solid fa-user-shield', 'Roles and permissions'],
        'super-admin.schools.index' => ['fa-solid fa-school', 'Every school on AkademicNest'],

        // --- Academic --------------------------------------------------
        'academics.index' => ['fa-solid fa-layer-group', 'Levels, classes and terms'],
        'class-subjects.index' => ['fa-solid fa-book-open', 'Subjects offered per class'],
        'attendance.index' => ['fa-solid fa-calendar-check', 'Daily registers'],
        'staff.attendance.index' => ['fa-solid fa-calendar-check', 'Mark your class register'],
        'student.attendance.index' => ['fa-solid fa-calendar-check', 'Your attendance record'],
        'guardian.children.attendance' => ['fa-solid fa-calendar-check', "Your child's attendance"],
        'timetable.index' => ['fa-solid fa-table-list', 'Class timetables'],
        'staff.timetable' => ['fa-solid fa-table-list', 'Your teaching schedule'],
        'student.timetable' => ['fa-solid fa-table-list', 'Your weekly schedule'],
        'guardian.children.timetable' => ['fa-solid fa-table-list', "Your child's schedule"],
        'student.subjects' => ['fa-solid fa-book-open', 'What you are studying'],

        // --- Examinations and results ----------------------------------
        'examinations.index' => ['fa-solid fa-file-pen', 'Set up examinations'],
        'examinations.score-entry' => ['fa-solid fa-file-pen', 'Enter test and exam marks'],
        'staff.exams.index' => ['fa-solid fa-file-pen', 'Enter marks for your subjects'],
        'results.index' => ['fa-solid fa-square-poll-vertical', 'Report cards and remarks'],
        'staff.results.index' => ['fa-solid fa-square-poll-vertical', "Your class's report cards"],
        'student.results.index' => ['fa-solid fa-square-poll-vertical', 'Your results'],
        'guardian.children.results' => ['fa-solid fa-square-poll-vertical', "Your child's results"],
        'result-repository.index' => ['fa-solid fa-database', 'Results you have published'],
        'result-pins.index' => ['fa-solid fa-key', 'Issue tokens to parents'],
        'super-admin.result-pins.index' => ['fa-solid fa-key', 'Token usage across schools'],

        // --- Work set and done -----------------------------------------
        'assignments.index' => ['fa-solid fa-list-check', 'Homework set to classes'],
        'student.assignments.index' => ['fa-solid fa-list-check', 'Your homework'],
        'guardian.children.assignments' => ['fa-solid fa-list-check', "Your child's homework"],

        // --- CBT -------------------------------------------------------
        'cbt-practice.index' => ['fa-solid fa-laptop', 'Past-question practice'],
        'student.cbt-practice.index' => ['fa-solid fa-laptop', 'Practise past questions'],
        'cbt-tests.index' => ['fa-solid fa-laptop-file', 'School computer-based tests'],
        'staff.cbt.tests.index' => ['fa-solid fa-laptop-file', 'Build and publish CBTs'],
        'student.tests.index' => ['fa-solid fa-laptop-file', 'Tests set by your school'],
        'super-admin.cbt.index' => ['fa-solid fa-laptop', 'Exam bodies and questions'],
        'super-admin.cbt.uploads.index' => ['fa-solid fa-file-arrow-up', 'Turn a paper into a CBT'],

        // --- Communication ---------------------------------------------
        'notices.index' => ['fa-solid fa-bullhorn', 'Memoranda to the school'],
        'communications.index' => ['fa-solid fa-comments', 'Messages and announcements'],
        'super-admin.communications.index' => ['fa-solid fa-comments', 'Announcements to schools'],
        'student.messages.index' => ['fa-solid fa-envelope', 'Messages from your school'],
        'guardian.messages.index' => ['fa-solid fa-envelope', 'Messages from the school'],
        'student.notifications.index' => ['fa-solid fa-bell', 'What you have missed'],
        'guardian.notifications.index' => ['fa-solid fa-bell', 'What you have missed'],
        'diary.index' => ['fa-solid fa-folder', 'What teachers taught each week'],
        'staff.diary.index' => ['fa-solid fa-folder', 'Record what you taught'],

        // --- School life -----------------------------------------------
        'library.index' => ['fa-solid fa-book-bookmark', 'Books and loans'],
        'student.library.index' => ['fa-solid fa-book-bookmark', 'Books you have borrowed'],
        'transport.index' => ['fa-solid fa-bus', 'Routes and pupil pickups'],
        'hostels.index' => ['fa-solid fa-bed', 'Rooms and allocations'],
        'co-curricular.index' => ['fa-solid fa-futbol', 'Clubs and activities'],
        'student.co-curricular.index' => ['fa-solid fa-futbol', 'Clubs you have joined'],
        'facilities.index' => ['fa-solid fa-building', 'What your school offers'],
        'events.index' => ['fa-solid fa-calendar-days', 'School calendar entries'],
        'news.index' => ['fa-solid fa-newspaper', 'Posts for your website'],
        'careers.index' => ['fa-solid fa-briefcase', 'Job postings'],
        'testimonials.index' => ['fa-solid fa-quote-left', 'What parents say'],

        // --- Money -----------------------------------------------------
        'finance.index' => ['fa-solid fa-sack-dollar', 'Fees, invoices and payments'],
        'guardian.children.fees' => ['fa-solid fa-receipt', 'Fees and payments'],
        'super-admin.payments.index' => ['fa-solid fa-credit-card', 'Payments received'],
        'super-admin.payment-settings.index' => ['fa-solid fa-money-check-dollar', 'How schools may pay'],
        'super-admin.subscriptions.index' => ['fa-solid fa-file-invoice-dollar', 'Plans awaiting approval'],

        // --- Identity --------------------------------------------------
        'id-cards.index' => ['fa-solid fa-id-card', 'Design and issue cards'],
        'staff.id-card.show' => ['fa-solid fa-id-card', 'Your staff card'],
        'student.id-card.show' => ['fa-solid fa-id-card', 'Your pupil card'],
        'staff.profile' => ['fa-solid fa-user', 'Your details'],
        'student.profile' => ['fa-solid fa-user', 'Your details'],
        'guardian.children.profile' => ['fa-solid fa-child', "Your child's details"],
        'profile-change-requests.index' => ['fa-solid fa-user-pen', 'Requests awaiting approval'],

        // --- Web presence ----------------------------------------------
        'website.index' => ['fa-solid fa-globe', "Your school's public site"],
        'super-admin.cms.index' => ['fa-solid fa-pen-ruler', 'Platform pages and content'],
        'super-admin.media.index' => ['fa-solid fa-photo-film', 'Images and video'],
        'super-admin.themes.index' => ['fa-solid fa-palette', 'Platform appearance'],

        // --- Platform --------------------------------------------------
        'super-admin.analytics.index' => ['fa-solid fa-chart-line', 'Usage across the platform'],
        'super-admin.reports.index' => ['fa-solid fa-flag', 'Misconduct reports'],
        'super-admin.audit-logs.index' => ['fa-solid fa-clock-rotate-left', 'Who did what, and when'],
        'super-admin.support-tickets.index' => ['fa-solid fa-headset', 'Schools needing help'],

        // --- Settings and help -----------------------------------------
        'settings.index' => ['fa-solid fa-gear', 'School details and preferences'],
        'staff.settings.index' => ['fa-solid fa-gear', 'Your preferences'],
        'student.settings.index' => ['fa-solid fa-gear', 'Your preferences'],
        'guardian.settings.index' => ['fa-solid fa-gear', 'Your preferences'],
        'super-admin.settings.index' => ['fa-solid fa-gear', 'Platform configuration'],
        'staff.help.index' => ['fa-solid fa-circle-question', 'Guides and support'],
        'student.help.index' => ['fa-solid fa-circle-question', 'Guides and support'],
        'guardian.help.index' => ['fa-solid fa-circle-question', 'Guides and support'],
    ];

    public static function icon(?string $route): string
    {
        return self::META[$route][0] ?? self::FALLBACK_ICON;
    }

    /**
     * The line under the name. Empty when there is nothing useful to add -
     * better a name on its own than a description that repeats it.
     */
    public static function description(?string $route): string
    {
        return self::META[$route][1] ?? '';
    }

    /**
     * @return list<string>
     */
    public static function routes(): array
    {
        return array_keys(self::META);
    }
}
