<?php

namespace App\Enums;

/**
 * What a cumulative session average is divided by.
 *
 * Only matters when a student is missing a term's result (joined in Second
 * Term, or a term not yet entered). With every term present both give the
 * same answer.
 */
enum CumulativeAverageBasis: string
{
    /** Average of the terms the student has a result for. */
    case TermsTaken = 'terms_taken';

    /** Always divided by three; a missing term counts as zero. */
    case AllTerms = 'all_terms';

    public function label(): string
    {
        return match ($this) {
            self::TermsTaken => 'Average of the terms the student has results for',
            self::AllTerms => 'Always divide by three terms (a missing term counts as zero)',
        };
    }

    public function divisor(int $termsWithResults): int
    {
        return match ($this) {
            self::TermsTaken => $termsWithResults,
            self::AllTerms => count(ExamTerm::cases()),
        };
    }
}
