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

    /**
     * Does a school pay into a bank account for this method?
     *
     * Only Bank Transfer does. Paystack collects the money itself, so bank
     * fields on its form are not merely unused - they are a trap: a Super
     * Admin who types their account number into the Paystack card has edited
     * a row nothing reads, and every school still sees the old details.
     * That is exactly how "I updated the payment settings and nothing
     * changed" happens.
     */
    public function usesBankAccount(): bool
    {
        return $this === self::BankTransfer;
    }
}
