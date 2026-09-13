<?php

namespace App\Enums;

/**
 * The kinds of extra question a school can ask applicants.
 */
enum JobQuestionType: string
{
    case ShortText = 'short_text';
    case LongText = 'long_text';
    case YesNo = 'yes_no';
    case Choice = 'choice';

    public function label(): string
    {
        return match ($this) {
            self::ShortText => 'Short answer',
            self::LongText => 'Paragraph',
            self::YesNo => 'Yes / No',
            self::Choice => 'Choose one option',
        };
    }
}
