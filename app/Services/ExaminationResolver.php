<?php

namespace App\Services;

use App\Enums\ExamTerm;
use App\Models\Examination;
use App\Models\School;

/**
 * The examination a batch of result tokens belongs to, worked out rather than
 * picked.
 *
 * A token is bound to one examination, and that binding is what makes "valid
 * for this term only" true, an examination IS one class, in one term, of one
 * session. So the binding cannot go away. What can go away is asking the
 * School Admin to choose it: they have already chosen the year, the term and
 * the class, and those three name the examination completely.
 *
 * The picker they replace was worse than redundant. An examination has to
 * exist before it can be chosen, so a school with none, which is every school
 * on its first term, met an empty dropdown reading "No examinations recorded
 * for that year and term yet" and could not issue a single token until it had
 * been somewhere else first.
 */
class ExaminationResolver
{
    /**
     * The examination for this class, term and session, creating it if the
     * school has not recorded one yet.
     *
     * WHERE SEVERAL MATCH, THE LATEST BY DATE WINS. A school may legitimately
     * hold a mid-term test and an end-of-term examination for the same class
     * and term; a result token is for the one that closes the term, which is
     * the later of them. Ties fall to the most recently created, so the answer
     * is always the same one rather than whichever the database happened to
     * return first.
     */
    public function forTokens(School $school, string $session, ExamTerm $term, string $className): Examination
    {
        $existing = Examination::query()
            ->where('school_id', $school->id)
            ->where('session', $session)
            ->where('term', $term)
            ->where('class_name', $className)
            ->orderByRaw('exam_date IS NULL')
            ->orderByDesc('exam_date')
            ->orderByDesc('id')
            ->first();

        if ($existing) {
            return $existing;
        }

        // Created here, deliberately. The alternative is refusing until the
        // school visits the Examinations module, which is the dead end this
        // replaces. Nothing is invented: the year, term and class are the
        // ones the School Admin just chose, and the name is derived from the
        // term rather than made up.
        return Examination::create([
            'school_id' => $school->id,
            'session' => $session,
            'term' => $term,
            'class_name' => $className,
            'name' => $term->label().' Examination',

            // Left NULL rather than dated today. A school that later sets the
            // real date should not find this one competing with it, and a
            // date nobody chose is not a fact worth recording.
            'exam_date' => null,
        ]);
    }
}
