<?php

namespace App\Support;

use App\Enums\IdCardHolderType;
use App\Models\IssuedIdCard;

/**
 * The detail rows printed on the front of a card.
 *
 * Which rows appear, in which order, under which labels, was written out three
 * separate times - in the screen card, in the PDF card, and again in the PDF's
 * shared row partial - each with its own copy of the conditions. Three copies
 * of "show House only for a pupil who has one" is three chances for a card to
 * print something a school did not intend, and for the printed card to differ
 * from the one the School Admin approved on screen.
 *
 * They are built once here. Both faces render the same list, so the preview
 * and the print cannot disagree.
 */
final class IdCardFields
{
    /**
     * @return list<array{label: string, value: string, icon: ?string}>
     */
    public static function rows(IssuedIdCard $card): array
    {
        $holder = $card->holder();

        if ($holder === null) {
            return [];
        }

        $school = $card->school;
        $template = $card->template;
        $isStudent = $card->holder_type === IdCardHolderType::Student;

        $candidates = [
            $isStudent
                ? ['Admission No.', $holder->admission_number]
                : ['Staff ID', $holder->staff_number],

            $isStudent
                ? ['Class', $holder->class_name]
                : ['Department', $holder->department ?? $holder->role->label()],

            ['D.O.B', $holder->date_of_birth?->format('jS F, Y')],

            // House is a pupil's, and only when the school records one.
            ['House', $isStudent ? $holder->house : null],

            ['Session', $school->current_session],

            // Both of these are off unless switched on: blood group by the
            // school's own template setting, expiry by the card having a date.
            ['Blood Group', $template?->show_blood_group ? $holder->blood_group : null],

            ['Expires', $card->expiry_date?->format('jS F, Y')],
        ];

        $rows = [];

        foreach ($candidates as [$label, $value]) {
            if (blank($value)) {
                continue;
            }

            $rows[] = [
                'label' => $label,
                'value' => (string) $value,
                'icon' => IdCardFieldIcon::dataUri($label),
            ];
        }

        return $rows;
    }
}
