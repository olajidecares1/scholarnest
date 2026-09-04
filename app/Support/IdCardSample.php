<?php

namespace App\Support;

use App\Enums\IdCardHolderType;
use App\Enums\IdCardOrientation;
use App\Enums\StaffRole;
use App\Models\IdCardTemplate;
use App\Models\IssuedIdCard;
use App\Models\School;
use App\Models\Staff;
use App\Models\Student;

/**
 * A specimen card, for the School Admin to look at before they commit.
 *
 * The template editor used to draw its own miniature of a card - a gradient
 * header, a small photo box, an "Authorized Signature" line - built by hand in
 * the editor's markup. None of it was the card. It was written once, the real
 * card moved on, and the two drifted until the preview a School Admin approved
 * bore no relation to what the printer produced.
 *
 * So the sample is now the REAL card, rendered by the real templates, from
 * invented details. Nothing here is saved: the holder is an unsaved model
 * handed straight to the card, and the template is an unsaved model carrying
 * whatever colours the editor currently has. Change a colour and this is
 * exactly what changes on the card.
 */
final class IdCardSample
{
    /**
     * Build a specimen card for a school, in the colours given.
     *
     * @param  array{primary_color?: ?string, secondary_color?: ?string, accent_color?: ?string, instructions?: ?string, show_blood_group?: bool}  $overrides
     */
    public static function for(
        School $school,
        IdCardHolderType $type = IdCardHolderType::Student,
        IdCardOrientation $orientation = IdCardOrientation::Portrait,
        array $overrides = [],
    ): IssuedIdCard {
        $defaults = IdCardDesign::newTemplateDefaults($school);

        $template = new IdCardTemplate([
            'name' => 'Sample',
            'type' => $type,
            'orientation' => $orientation,
            // ?? before ?:, because $overrides is documented as optional and
            // every key in it is too - reading a missing one directly threw.
            'primary_color' => ($overrides['primary_color'] ?? null) ?: $defaults['primary_color'],
            'secondary_color' => ($overrides['secondary_color'] ?? null) ?: $defaults['secondary_color'],
            'accent_color' => ($overrides['accent_color'] ?? null) ?: $defaults['accent_color'],
            'instructions' => $overrides['instructions'] ?? null,
            'show_blood_group' => (bool) ($overrides['show_blood_group'] ?? false),
        ]);

        $template->school_id = $school->id;

        $card = new IssuedIdCard([
            'holder_type' => $type,
            'card_number' => 'SAMPLE-0001',
        ]);

        $card->school_id = $school->id;

        // uuid, because verificationUrl() builds a route from the card and a
        // sample has never been saved to have one of its own.
        $card->uuid = '00000000-0000-4000-8000-000000000000';

        $card->setRelation('school', $school);
        $card->setRelation('template', $template);

        return $card->withHolder(self::holder($school, $type));
    }

    /**
     * The invented pupil or staff member on the specimen.
     *
     * Deliberately complete - a name of a realistic length, a class, a house,
     * a date of birth - because the point of a specimen is to show a School
     * Admin how their card looks when it is full, not how it looks empty.
     */
    private static function holder(School $school, IdCardHolderType $type): Student|Staff
    {
        if ($type === IdCardHolderType::Student) {
            $student = new Student([
                'first_name' => 'Chinedu',
                'last_name' => 'Okafor',
                'admission_number' => 'SAMPLE/2024/001',
                'class_name' => 'Primary 6',
                'house' => 'Blue House',
                'date_of_birth' => '2013-05-12',
                'blood_group' => 'O+',
            ]);

            $student->school_id = $school->id;

            return $student;
        }

        $staff = new Staff([
            'first_name' => 'Amaka',
            'last_name' => 'Balogun',
            'staff_number' => 'SAMPLE/STF/001',
            'department' => 'Mathematics',
            'date_of_birth' => '1990-03-04',
            'blood_group' => 'A+',
            'role' => $type === IdCardHolderType::TeachingStaff ? StaffRole::Teacher : StaffRole::Administrator,
        ]);

        $staff->school_id = $school->id;

        return $staff;
    }
}
