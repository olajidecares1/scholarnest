<?php

namespace App\Support;

use App\Enums\MemorandumAudience;
use App\Enums\PlanKey;
use App\Enums\StaffRole;
use App\Models\Guardian;
use App\Models\School;
use App\Models\SchoolNotice;
use App\Models\SchoolNoticeRead;
use App\Models\Staff;
use App\Models\Student;
use Illuminate\Support\Facades\Route;

/**
 * What each portal offers its user, grouped the way a phone app groups things.
 *
 * The three portals used to define their own navigation inline - once for the
 * sidebar, again for the bottom bar, and a third time for the "More" sheet,
 * each with its own copy of the plan and role rules. Three copies of a rule is
 * three chances for one of them to drift, and the one that drifts is the one
 * that shows a Basic school a link that 403s.
 *
 * So it is defined once, here, and every surface reads from it.
 *
 * Two rules are applied to every item before it is returned:
 *
 * 1. The route must exist. A link to a route that was never registered is a
 *    500 waiting for someone to tap it.
 * 2. The school's plan and the user's role must permit it. Hiding a link is
 *    not access control - the routes enforce that themselves - but showing a
 *    link that refuses the tap is a broken portal, so both must agree.
 */
final class PortalNavigation
{
    /**
     * The staff portal.
     *
     * @return array{categories: array<string, list<array<string, mixed>>>, primary: list<array<string, mixed>>, account: list<array<string, mixed>>}
     */
    public static function forStaff(Staff $staff): array
    {
        $school = $staff->school;
        $isTeacher = $staff->role === StaffRole::Teacher;

        // CBT, ID cards, the diary and the timetable are Standard and
        // Exclusive features. The staff portal itself is open to every plan,
        // so a Basic school reaches the portal but not these - and their
        // routes say so with a 403.
        $premium = $school->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive);

        $categories = [
            'Academic' => self::items($school, [
                ['staff.attendance.index', 'Attendance', $isTeacher],
                ['staff.exams.index', 'Test/Exam Score', $isTeacher],
                ['staff.results.index', 'Report Cards', $isTeacher],

                // Every member of staff, on every plan - not $isTeacher and
                // not $premium. Sending a class its notes is the school's own
                // teaching work rather than an outward-facing extra, and a
                // Basic school's staff use it exactly as any other school's.
                ['staff.class-notes.index', 'Class Note', true],
                ['staff.cbt.tests.index', 'CBT', $isTeacher && $premium],
                ['staff.diary.index', 'Diary', $isTeacher && $premium],
                ['staff.timetable', 'My Timetable', $premium],
            ]),
            'Account' => self::items($school, [
                ['staff.profile', 'Profile', true],
                ['staff.id-card.show', 'ID Card', $premium],
                ['staff.settings.index', 'Settings', true],
                ['staff.help.index', 'Help & Support', true],
            ]),
        ];

        return [
            'categories' => array_filter($categories),
            'primary' => self::items($school, [
                ['staff.dashboard', 'Home', true],
                ['staff.attendance.index', 'Attendance', $isTeacher],
                ['staff.exams.index', 'Scores', $isTeacher],
            ]),
            'account' => $categories['Account'],
        ];
    }

    /**
     * The student portal.
     *
     * Note what is absent: nothing here lets a pupil edit their own details or
     * change their own password. That is a standing rule of this application,
     * and the surest way to keep a link off a page is never to build it.
     *
     * @return array{categories: array<string, list<array<string, mixed>>>, primary: list<array<string, mixed>>, account: list<array<string, mixed>>}
     */
    public static function forStudent(Student $student): array
    {
        $school = $student->school;

        $categories = [
            'Academic' => self::items($school, [
                ['student.results.index', 'Results', true],
                ['student.tests.index', 'My Tests', true],
                ['student.cbt-practice.index', 'CBT', true],
                ['student.attendance.index', 'Attendance', true],
                ['student.assignments.index', 'Assignments', true],

                // Only ever reached on Standard and Exclusive: this whole menu
                // belongs to the student portal, which Basic does not have.
                ['student.class-notes.index', 'Class Notes', true],

                ['student.subjects', 'My Subjects', true],
                ['student.timetable', 'Timetable', true],
                ['student.library.index', 'Library', true],
                ['student.co-curricular.index', 'Co-curricular', true],
            ]),
            'Communication' => self::items($school, [
                ['student.messages.index', 'Messages', true],
                ['student.notifications.index', 'Notifications', true],
            ]),
            'Account' => self::items($school, [
                ['student.profile', 'Profile', true],
                ['student.id-card.show', 'ID Card', true],
                ['student.settings.index', 'Settings', true],
                ['student.help.index', 'Help & Support', true],
            ]),
        ];

        return [
            'categories' => array_filter($categories),
            'primary' => self::items($school, [
                ['student.dashboard', 'Home', true],
                ['student.results.index', 'Results', true],
                ['student.cbt-practice.index', 'CBT', true],
            ]),
            'account' => $categories['Account'],
        ];
    }

    /**
     * The parent/guardian portal.
     *
     * Every academic entry is bound to one child, because that is what a
     * parent is actually looking at: not "results" but "Ada's results". The
     * child is carried in the URL, so switching child switches the whole menu
     * rather than leaving a stale link pointing at a sibling.
     *
     * @return array{categories: array<string, list<array<string, mixed>>>, primary: list<array<string, mixed>>, account: list<array<string, mixed>>}
     */
    public static function forGuardian(Guardian $guardian, ?Student $child = null): array
    {
        $school = $guardian->school;

        $categories = [
            'Academic' => $child === null ? [] : self::items($school, [
                ['guardian.children.results', 'Check Result', true],
                ['guardian.children.attendance', 'Attendance', true],
                ['guardian.children.assignments', 'Assignments', true],
                ['guardian.children.timetable', 'Timetable', true],
                ['guardian.children.fees', 'Fees', true],
                ['guardian.children.profile', 'Child Profile', true],
            ], [$school, $child]),
            'Communication' => self::items($school, [
                ['guardian.messages.index', 'Messages', true],
                ['guardian.notifications.index', 'Notifications', true],
            ]),
            'Account' => self::items($school, [
                ['guardian.settings.index', 'Settings', true],
                ['guardian.help.index', 'Help & Support', true],
            ]),
        ];

        return [
            'categories' => array_filter($categories),
            'primary' => array_merge(
                self::items($school, [['guardian.dashboard', 'Home', true]]),
                $child === null ? [] : self::items($school, [
                    ['guardian.children.results', 'Results', true],
                    ['guardian.children.attendance', 'Attendance', true],
                ], [$school, $child]),
            ),
            'account' => $categories['Account'],
        ];
    }

    /**
     * Unread notifications for any portal user that can receive them.
     */
    public static function unreadNotifications(mixed $user): ?int
    {
        if (! method_exists($user, 'unreadNotifications')) {
            return null;
        }

        return $user->unreadNotifications()->count();
    }

    /**
     * Unread memoranda.
     *
     * Only pupils have read receipts (school_notice_reads is keyed by
     * student_id), so only pupils get a number. For everyone else this returns
     * null and the icon shows no badge, rather than a count invented from
     * whatever data happened to be to hand.
     */
    public static function unreadMessages(mixed $user): ?int
    {
        if (! $user instanceof Student) {
            return null;
        }

        $readIds = SchoolNoticeRead::query()
            ->where('student_id', $user->id)
            ->pluck('school_notice_id');

        return SchoolNotice::query()
            ->where('school_id', $user->school_id)
            ->forAudience(MemorandumAudience::Students)
            ->where(fn ($query) => $query->whereNull('class_name')->orWhere('class_name', $user->class_name))
            ->whereNotIn('id', $readIds)
            ->count();
    }

    /**
     * Turn route/label/permitted triples into renderable items, dropping the
     * ones this user may not have.
     *
     * @param  list<array{0: string, 1: string, 2: bool}>  $definitions
     * @param  list<mixed>|null  $parameters  Route parameters; defaults to the school alone.
     * @return list<array<string, mixed>>
     */
    private static function items(School $school, array $definitions, ?array $parameters = null): array
    {
        $items = [];

        foreach ($definitions as [$route, $label, $permitted]) {
            if (! $permitted || ! Route::has($route) || ! $school->canAccessRoute($route)) {
                continue;
            }

            $items[] = [
                'route' => $route,
                'label' => $label,
                'icon' => SidebarMeta::icon($route),
                'description' => SidebarMeta::description($route),
                'url' => route($route, $parameters ?? [$school]),
                'active' => request()->routeIs($route) || request()->routeIs($route.'.*'),
            ];
        }

        return $items;
    }
}
