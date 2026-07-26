<?php

namespace App\Enums;

enum SubscriptionStatus: string
{
    case PendingPayment = 'pending_payment';
    case PendingVerification = 'pending_verification';
    case Active = 'active';
    case Rejected = 'rejected';
    case Expired = 'expired';

    public function label(): string
    {
        return match ($this) {
            self::PendingPayment => 'Pending Payment',
            self::PendingVerification => 'Pending Verification',
            self::Active => 'Active',
            self::Rejected => 'Rejected',
            self::Expired => 'Expired',
        };
    }
}
