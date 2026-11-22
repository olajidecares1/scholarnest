<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Models\AcademicTerm;
use App\Models\Examination;
use App\Models\ExaminationSubject;
use App\Models\School;
use Illuminate\Support\Facades\DB;

/**
 * Keeps an examination's subjects (what a teacher's score sheet lists) in
 * step with the subjects its class offers.
 *
 * Scores are entered against an examination's subjects. Before this, an
 * examination started with none, and a class's subjects reached it only if
 * an administrator added them one by one, so a class teacher opened "Test/
 * Exam Score" to an empty page, or to a few subjects out of the class's
 * full list. Now:
 *
 *  - a new examination starts with every subject the class offers;
 *  - an examination in the current session picks up any subject the class
 *    offers that it is missing (a default list that grew, a subject the
 *    admin ticked later);
 *  - a platform subject the class no longer offers comes off this session's
 *    examinations, but only while nobody has entered a score in it, and
 *    never from an examination whose results are already published;
 *  - a class teacher's class gets this term's examination created when it
 *    has none, so there is always a sheet to score.
 *
 * A subject an administrator added to one examination by hand, that is not
 * a platform subject, is never removed.
 */
class ExaminationSubjectSync
{
    /**
     * Brings one examination in line with its class's subjects. Returns how
     * many subjects were added and removed.
     *
     * @return array{added: int, removed: int}
     */
    public static function sync(Examination $examination): array
    {
        $school = $examination->school;

        if (! $school) {
            return ['added' => 0, 'removed' => 0];
        }

        $className = (string) $examination->class_name;
        $offered = $school->offeredSubjectsFor($className);

        if ($offered->isEmpty()) {
            return ['added' => 0, 'removed' => 0];
        }

        $group = DefaultClassSubjects::groupForSchoolClassName((int) $school->id, $className);
        $existing = $examination->subjects()->withCount('scores')->get();

        return DB::transaction(function () use ($examination, $offered, $group, $existing) {
            $added = 0;
            $removed = 0;

            foreach ($offered as $name) {
                $already = $existing->contains(fn (ExaminationSubject $subject) => DefaultClassSubjects::sameSubject($subject->name, $name, $group));

                if (! $already) {
                    $examination->subjects()->firstOrCreate(['name' => $name], ['max_score' => 100]);
                    $added++;
                }
            }

            // Nothing comes off a paper whose results are already out.
            if ($examination->repositoryResults()->exists()) {
                return ['added' => $added, 'removed' => 0];
            }

            foreach ($existing as $subject) {
                $stillOffered = $offered->contains(fn ($name) => DefaultClassSubjects::sameSubject($subject->name, $name, $group));

                if (! $stillOffered
                    && (int) $subject->scores_count === 0
                    && DefaultClassSubjects::isPlatformSubject($subject->name)) {
                    $subject->delete();
                    $removed++;
                }
            }

            return ['added' => $added, 'removed' => $removed];
        });
    }

    /**
     * Brings every examination of a class in a session (by default the
     * school's current one) in line. Returns how many subjects were added.
     */
    public static function syncClass(School $school, string $className, ?string $session = null): int
    {
        $session ??= $school->currentSession();

        return $school->examinations()
            ->where('class_name', $className)
            ->where('session', $session)
            ->get()
            ->sum(fn (Examination $examination) => self::sync($examination)['added']);
    }

    /**
     * Makes sure a class has an examination for the current term, with its
     * subjects, and brings the class's examinations this session in line.
     * Used when a teacher opens their score page, so a class with subjects
     * is never shown as having nothing to score.
     *
     * Nothing is created for a class that offers no subjects.
     *
     * A teacher who picks another term of the current session (second-term
     * scores entered before the school has set the term's dates) gets that
     * term's examination the same way; $term names it.
     */
    public static function prepareForScoring(School $school, string $className, ?ExamTerm $term = null): void
    {
        if ($school->subjectOfferings()->where('class_name', $className)->doesntExist()) {
            return;
        }

        [$session, $currentTerm] = self::currentTerm($school);
        $term ??= $currentTerm;

        $exists = $school->examinations()
            ->where('class_name', $className)
            ->where('session', $session)
            ->where('term', $term)
            ->exists();

        if (! $exists) {
            // The same examination the result-token screen would create for
            // this class and term (App\Services\ExaminationResolver), so the
            // two never end up with one each.
            $school->examinations()->create([
                'class_name' => $className,
                'session' => $session,
                'term' => $term,
                'name' => $term->label().' Examination',
                'exam_date' => null,
            ]);
        }

        self::syncClass($school, $className, $session);
    }

    /**
     * The school's current session and term: the term the school set up
     * that today falls in; between terms, the last one that has started;
     * with no terms set up, the term the calendar says (September to
     * December first, January to April second, May to August third).
     *
     * @return array{0: string, 1: ExamTerm}
     */
    public static function currentTerm(School $school): array
    {
        $session = $school->currentSession();

        $term = AcademicTerm::currentFor($school)
            ?? AcademicTerm::where('school_id', $school->id)
                ->where('session', $session)
                ->whereDate('starts_on', '<=', today())
                ->orderByDesc('starts_on')
                ->first();

        if ($term) {
            return [$term->session, $term->term];
        }

        $month = now()->month;

        return [$session, match (true) {
            $month >= 9 => ExamTerm::First,
            $month <= 4 => ExamTerm::Second,
            default => ExamTerm::Third,
        }];
    }
}
