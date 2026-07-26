<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case BankTransfer = 'bank_transfer';
    case Paystack = 'paystack';

    public function label(): string
    {
        return match ($this) {
            self::BankTransfer => 'Bank Transfer',
            self::Paystack => 'Paystack (Card, USSD, Transfer)',
        };
    }
}
