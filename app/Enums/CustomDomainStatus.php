<?php

namespace App\Enums;

enum CustomDomainStatus: string
{
    case PendingVerification = 'pending_verification';
    case Verified = 'verified';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::PendingVerification => 'Pending Verification',
            self::Verified => 'Verified',
            self::Failed => 'Verification Failed',
        };
    }
}
