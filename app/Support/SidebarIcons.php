<?php

namespace App\Support;

/**
 * The Font Awesome icon for each place in the application.
 *
 * Keyed by ROUTE, not by label, because five sidebars use different words for
 * the same destination - "Test/Exam Score" in one portal, "Report Cards" in
 * another - and an icon should follow the place rather than the wording.
 *
 * One map rather than an icon stored beside each menu entry in each layout.
 * The same destination appearing in the School Admin's sidebar and a teacher's
 * should look the same, and it will not if the icon is chosen five times.
 *
 * The choices are literal on purpose: a graduation cap for pupils, a
 * clipboard-with-a-tick for attendance, a lock for tokens. A sidebar is read at
 * a glance and the icon is the part that has to survive being glanced at.
 */
class SidebarIcons
{
    /**
     * Anything not named here. Deliberately neutral rather than clever - a
     * wrong icon is worse than a plain one.
     */
    public const FALLBACK = 'fa-solid fa-circle-dot';

    /**
     * @var array<string, string>
     */
    private const ICONS = [
        // --- Everywhere ------------------------------------------------
        'dashboard' => 'fa-solid fa-gauge-high',
        'staff.dashboard' => 'fa-solid fa-gauge-high',
        'student.dashboard' => 'fa-solid fa-gauge-high',
        'guardian.dashboard' => 'fa-solid fa-gauge-high',
        'super-admin.dashboard' => 'fa-solid fa-gauge-high',

        // --- People ----------------------------------------------------
        'students.index' => 'fa-solid fa-graduation-cap',
        'guardians.index' => 'fa-solid fa-people-roof',
        'staff.index' => 'fa-solid fa-chalkboard-user',
        'teacher-assignments.index' => 'fa-solid fa-user-check',
        'super-admin.users.index' => 'fa-solid fa-users',
        'super-admin.roles.index' => 'fa-solid fa-user-shield',
        'super-admin.schools.index' => 'fa-solid fa-school',

        // --- Academic --------------------------------------------------
        'academics.index' => 'fa-solid fa-layer-group',
        'class-subjects.index' => 'fa-solid fa-book-open',
        'attendance.index' => 'fa-solid fa-clipboard-check',
        'staff.attendance.index' => 'fa-solid fa-clipboard-check',
        'student.attendance.index' => 'fa-solid fa-clipboard-check',
        'guardian.children.attendance' => 'fa-solid fa-clipboard-check',
        'timetable.index' => 'fa-solid fa-calendar-days',
        'staff.timetable' => 'fa-solid fa-calendar-days',
        'student.timetable' => 'fa-solid fa-calendar-days',
        'guardian.children.timetable' => 'fa-solid fa-calendar-days',
        'student.subjects' => 'fa-solid fa-book-open',

        // --- Examinations and results ----------------------------------
        'examinations.index' => 'fa-solid fa-file-pen',
        'examinations.score-entry' => 'fa-solid fa-pen-to-square',
        'staff.exams.index' => 'fa-solid fa-pen-to-square',
        'results.index' => 'fa-solid fa-square-poll-vertical',
        'staff.results.index' => 'fa-solid fa-square-poll-vertical',
        'student.results.index' => 'fa-solid fa-square-poll-vertical',
        'guardian.children.results' => 'fa-solid fa-square-poll-vertical',
        'result-repository.index' => 'fa-solid fa-database',
        'result-pins.index' => 'fa-solid fa-key',
        'super-admin.result-pins.index' => 'fa-solid fa-key',

        // --- Work set and done -----------------------------------------
        'assignments.index' => 'fa-solid fa-list-check',
        'student.assignments.index' => 'fa-solid fa-list-check',
        'guardian.children.assignments' => 'fa-solid fa-list-check',

        // --- CBT -------------------------------------------------------
        'cbt-practice.index' => 'fa-solid fa-laptop-code',
        'student.cbt-practice.index' => 'fa-solid fa-laptop-code',
        'cbt-tests.index' => 'fa-solid fa-desktop',
        'staff.cbt.tests.index' => 'fa-solid fa-desktop',
        'student.tests.index' => 'fa-solid fa-desktop',
        'super-admin.cbt.index' => 'fa-solid fa-desktop',

        // --- Communication ---------------------------------------------
        'notices.index' => 'fa-solid fa-bullhorn',
        'communications.index' => 'fa-solid fa-comments',
        'super-admin.communications.index' => 'fa-solid fa-comments',
        'student.messages.index' => 'fa-solid fa-envelope',
        'guardian.messages.index' => 'fa-solid fa-envelope',
        'student.notifications.index' => 'fa-solid fa-bell',
        'guardian.notifications.index' => 'fa-solid fa-bell',
        'diary.index' => 'fa-solid fa-book',
        'staff.diary.index' => 'fa-solid fa-book',

        // --- School life -----------------------------------------------
        'library.index' => 'fa-solid fa-book-bookmark',
        'student.library.index' => 'fa-solid fa-book-bookmark',
        'transport.index' => 'fa-solid fa-bus',
        'hostels.index' => 'fa-solid fa-bed',
        'co-curricular.index' => 'fa-solid fa-futbol',
        'student.co-curricular.index' => 'fa-solid fa-futbol',
        'facilities.index' => 'fa-solid fa-building',
        'events.index' => 'fa-solid fa-calendar-star',
        'news.index' => 'fa-solid fa-newspaper',
        'careers.index' => 'fa-solid fa-briefcase',
        'testimonials.index' => 'fa-solid fa-quote-left',

        // --- Money -----------------------------------------------------
        'finance.index' => 'fa-solid fa-naira-sign',
        'guardian.children.fees' => 'fa-solid fa-receipt',
        'super-admin.payments.index' => 'fa-solid fa-credit-card',
        'super-admin.payment-settings.index' => 'fa-solid fa-money-check-dollar',
        'super-admin.subscriptions.index' => 'fa-solid fa-file-invoice-dollar',

        // --- Identity --------------------------------------------------
        'id-cards.index' => 'fa-solid fa-id-card',
        'staff.id-card.show' => 'fa-solid fa-id-card',
        'student.id-card.show' => 'fa-solid fa-id-card',
        'staff.profile' => 'fa-solid fa-user',
        'student.profile' => 'fa-solid fa-user',
        'guardian.children.profile' => 'fa-solid fa-child',
        'profile-change-requests.index' => 'fa-solid fa-user-pen',

        // --- Web presence ----------------------------------------------
        'website.index' => 'fa-solid fa-globe',
        'super-admin.cms.index' => 'fa-solid fa-pen-ruler',
        'super-admin.media.index' => 'fa-solid fa-photo-film',
        'super-admin.themes.index' => 'fa-solid fa-palette',

        // --- Platform --------------------------------------------------
        'super-admin.analytics.index' => 'fa-solid fa-chart-line',
        'super-admin.reports.index' => 'fa-solid fa-flag',
        'super-admin.audit-logs.index' => 'fa-solid fa-clock-rotate-left',
        'super-admin.support-tickets.index' => 'fa-solid fa-headset',

        // --- Settings and help -----------------------------------------
        'settings.index' => 'fa-solid fa-gear',
        'staff.settings.index' => 'fa-solid fa-gear',
        'student.settings.index' => 'fa-solid fa-gear',
        'guardian.settings.index' => 'fa-solid fa-gear',
        'super-admin.settings.index' => 'fa-solid fa-gear',
        'staff.help.index' => 'fa-solid fa-circle-question',
        'student.help.index' => 'fa-solid fa-circle-question',
        'guardian.help.index' => 'fa-solid fa-circle-question',
    ];

    /**
     * The icon for a route.
     */
    public static function for(?string $route): string
    {
        return self::ICONS[$route] ?? self::FALLBACK;
    }

    /**
     * Every route that has been given an icon.
     *
     * For the test that holds the map complete: a menu entry added without an
     * icon falls back to a plain dot, which is easy to miss in review and
     * obvious in a test.
     *
     * @return list<string>
     */
    public static function routes(): array
    {
        return array_keys(self::ICONS);
    }
}
