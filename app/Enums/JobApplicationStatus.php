<?php

namespace App\Enums;

/**
 * Where an applicant is in a school's recruitment.
 *
 * The School Admin may move an application to any status at any time - real
 * recruitment goes back as well as forward - and every move is recorded in
 * job_application_events.
 */
enum JobApplicationStatus: string
{
    case New = 'new';
    case UnderReview = 'under_review';
    case Shortlisted = 'shortlisted';
    case InterviewInvited = 'interview_invited';
    case Interviewed = 'interviewed';
    case Selected = 'selected';
    case Rejected = 'rejected';
    case Hired = 'hired';
    case Withdrawn = 'withdrawn';

    public function label(): string
    {
        return match ($this) {
            self::New => 'New',
            self::UnderReview => 'Under Review',
            self::Shortlisted => 'Shortlisted',
            self::InterviewInvited => 'Interview Invited',
            self::Interviewed => 'Interviewed',
            self::Selected => 'Selected',
            self::Rejected => 'Rejected',
            self::Hired => 'Hired',
            self::Withdrawn => 'Withdrawn',
        };
    }

    public function icon(): string
    {
        return match ($this) {
            self::New => 'fa-inbox',
            self::UnderReview => 'fa-magnifying-glass',
            self::Shortlisted => 'fa-list-check',
            self::InterviewInvited => 'fa-calendar-check',
            self::Interviewed => 'fa-comments',
            self::Selected => 'fa-star',
            self::Rejected => 'fa-circle-xmark',
            self::Hired => 'fa-user-check',
            self::Withdrawn => 'fa-arrow-right-from-bracket',
        };
    }

    public function badgeClasses(): string
    {
        return match ($this) {
            self::New => 'bg-blue-100 text-blue-700 dark:bg-blue-900/30 dark:text-blue-400',
            self::UnderReview => 'bg-indigo-100 text-indigo-700 dark:bg-indigo-900/30 dark:text-indigo-400',
            self::Shortlisted => 'bg-purple-100 text-purple-700 dark:bg-purple-900/30 dark:text-purple-400',
            self::InterviewInvited => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-400',
            self::Interviewed => 'bg-cyan-100 text-cyan-800 dark:bg-cyan-900/30 dark:text-cyan-400',
            self::Selected => 'bg-emerald-100 text-emerald-700 dark:bg-emerald-900/30 dark:text-emerald-400',
            self::Rejected => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            self::Hired => 'bg-green-100 text-green-800 dark:bg-green-900/30 dark:text-green-400',
            self::Withdrawn => 'bg-gray-100 text-gray-600 dark:bg-gray-700 dark:text-gray-300',
        };
    }

    /** Still somewhere in the process, rather than finished with. */
    public function isActive(): bool
    {
        return ! in_array($this, [self::Rejected, self::Hired, self::Withdrawn], true);
    }
}
