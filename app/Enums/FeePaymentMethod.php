<?php

namespace App\Enums;

enum FeePaymentMethod: string
{
    case Cash = 'cash';
    case BankTransfer = 'bank_transfer';
    case Pos = 'pos';
    case Cheque = 'cheque';
    case Other = 'other';

    public function label(): string
    {
        return match ($this) {
            self::Cash => 'Cash',
            self::BankTransfer => 'Bank Transfer',
            self::Pos => 'POS',
            self::Cheque => 'Cheque',
            self::Other => 'Other',
        };
    }
}
