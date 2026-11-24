<?php

namespace App\Support;

/**
 * Thirty ready-made Class Teacher's comments, offered to every class teacher
 * under each pupil's comment box alongside their own saved ones.
 *
 * Written without a name or a he/she, so any of them fits any pupil, and
 * spread across the range a class actually has: top of the class, steady,
 * improving, struggling, and the conduct and attendance notes teachers add.
 */
final class SuggestedTeacherRemarks
{
    public const ALL = [
        // Excellent
        'An outstanding performance this term. Keep up the excellent work.',
        'A brilliant and diligent learner who sets a fine example for the class.',
        'Excellent result. Consistent hard work has clearly paid off.',
        'A well-rounded learner who does well both in and out of the classroom.',
        'Shows great interest in learning and takes an active part in class.',

        // Good
        'A very good performance. With a little more focus, the top is within reach.',
        'A hardworking and well-behaved pupil. Keep it up.',
        'A good result. More attention to detail will bring even better grades.',
        'Neat, organised and always punctual. A pleasure to teach.',
        'A creative thinker who expresses ideas clearly and confidently.',

        // Improving
        'Steady improvement this term. Keep pushing forward.',
        'Has improved remarkably since last term. Well done.',
        'A promising learner. Continued support at home is encouraged.',
        'Has the ability to excel but must take studies more seriously.',
        'Capable of much better results with more concentration in class.',

        // Fair / needs effort
        'A fair performance. More effort is needed next term.',
        'An average result. Regular revision at home will help greatly.',
        'Needs to pay more attention in class and complete all assignments.',
        'Should spend more time on reading and practice exercises.',
        'Work is often rushed. Taking time to check answers will help.',

        // Struggling
        'Performance dropped this term. Close monitoring at home is advised.',
        'Has not done well this term and needs serious improvement.',
        'Needs extra support in Mathematics; strong in other subjects.',
        'Needs to improve in English, especially reading and spelling.',
        'Handwriting needs improvement; regular practice is encouraged.',

        // Conduct and attendance
        'Respectful, cooperative and gets along well with classmates.',
        'Shows good leadership qualities and is always willing to help others.',
        'Quiet and attentive; should be more confident to ask questions.',
        'Talks too much in class; better self-control will improve results.',
        'Must improve on punctuality and regular attendance.',
    ];

    /**
     * @return list<array{uuid: string, body: string}>
     */
    public static function forPicker(): array
    {
        return array_map(
            fn (string $body, int $i) => ['uuid' => 'suggested-'.($i + 1), 'body' => $body],
            self::ALL,
            array_keys(self::ALL),
        );
    }
}
