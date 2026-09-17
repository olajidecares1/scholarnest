<?php

namespace App\Support;

/**
 * Question wording as a candidate is allowed to see it.
 *
 * The importer already keeps answers out of the wording. This is the second
 * line, applied as the exam screen is drawn, for questions that were saved
 * before it did: a paper imported by the old reader carried "✓ Correct Answer:
 * A" inside its questions and showed it to every student who opened it. Those
 * questions should be read again from their document, but until somebody does,
 * the answer is not put on the screen.
 *
 * It also undoes HTML escaping the old reader stored ("De Graft&#039;s"),
 * since the exam screen escapes text itself and would otherwise show the code.
 */
final class CandidateText
{
    /**
     * "✓ Correct Answer: A", "Correct Answer - (c)". A single letter, and not
     * the start of a word, so "Correct Answer: Abuja" in a real question stays.
     */
    private const ANSWER_MARKER = '/\s*[✓✔]?\s*\bCorrect\s+Answer\s*[\:\-–]\s*\(?\s*[A-Ha-h]\s*\)?(?!\p{L})\.?/u';

    public static function clean(?string $text): ?string
    {
        if ($text === null) {
            return null;
        }

        $text = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

        return trim((string) preg_replace(self::ANSWER_MARKER, '', $text));
    }
}
