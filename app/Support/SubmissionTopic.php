<?php

namespace App\Support;

use Illuminate\Support\Str;

/**
 * The line the inbox lists a submission under.
 *
 * The inbox shows what each thing is ABOUT and keeps the body behind a click.
 * That needs every row to have a topic, and not every row has one to give: the
 * subject is optional on both public forms, deliberately, because a parent
 * asking about fees and a neighbour reporting a fight should not be made to
 * summarise before they are allowed to write.
 *
 * So a missing subject is derived from the body rather than left blank. A blank
 * cell in a list of otherwise-titled rows reads as a broken record, and
 * "(no subject)" tells an administrator nothing they could act on.
 *
 * The first SENTENCE, where the body has one, because that is usually the
 * whole of what happened - "Two pupils were fighting at the bus stop." Failing
 * that, the opening words. Either way it is trimmed to something that fits a
 * table row on a phone.
 */
final class SubmissionTopic
{
    private const LIMIT = 72;

    public static function from(?string $subject, ?string $body): string
    {
        if (filled($subject)) {
            return Str::limit(trim($subject), self::LIMIT);
        }

        $body = trim((string) $body);

        if ($body === '') {
            // Nothing at all to go on. Validation does not allow this from
            // either public form, so it only happens to a record written some
            // other way - and even then the row stays readable.
            return 'Untitled';
        }

        // Split on the first full stop, question mark or exclamation that is
        // followed by a space or the end of the text, so "3.30pm" and "Mr.
        // Adeyemi" do not end the sentence early.
        $sentence = preg_split('/(?<=[.?!])(?=\s|$)/', $body, 2)[0] ?? $body;
        $sentence = trim($sentence);

        // A "sentence" longer than the limit is not a summary of anything, so
        // fall back to the opening words rather than a truncated sentence that
        // happens to end mid-clause either way.
        $topic = mb_strlen($sentence) <= self::LIMIT ? $sentence : $body;

        return Str::limit(rtrim($topic, '.'), self::LIMIT);
    }
}
