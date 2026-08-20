<?php

namespace App\Enums;

enum SubscriptionTopUpStatus: string
{
    case PendingVerification = 'pending_verification';
    case Approved = 'approved';
    case Rejected = 'rejected';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending Verification',
            self::Approved => 'Approved',
            self::Rejected => 'Rejected',
        };
    }
}
