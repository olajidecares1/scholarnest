<?php

namespace App\Enums;

enum CustomDomainSslStatus: string
{
    case Pending = 'pending';
    case Issuing = 'issuing';
    case Active = 'active';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Issuing => 'Issuing Certificate',
            self::Active => 'Active',
            self::Failed => 'Failed',
        };
    }
}
