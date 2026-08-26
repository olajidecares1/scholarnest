<?php

namespace App\Enums;

enum PlanKey: string
{
    case Basic = 'basic';
    case Standard = 'standard';
    case Exclusive = 'exclusive';

    /**
     * The plan's name as a school sees it, so a message can say "the Standard
     * Plan" without the caller having to know how to spell it.
     */
    public function label(): string
    {
        return match ($this) {
            self::Basic => 'Basic Plan',
            self::Standard => 'Standard Plan',
            self::Exclusive => 'Exclusive Plan',
        };
    }
}
