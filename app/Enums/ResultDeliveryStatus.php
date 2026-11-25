<?php

namespace App\Enums;

/**
 * Where one message carrying a student's result details stands.
 *
 *   PENDING          created, not yet handed to the email server or WhatsApp.
 *   SENT             accepted by the email server, or by WhatsApp.
 *   DELIVERED        WhatsApp has confirmed it reached the phone (WhatsApp
 *                    only: email servers do not report delivery).
 *   FAILED           refused, with the reason. Can be retried.
 *   MISSING_CONTACT  the student's record has no valid address for this
 *                    channel. Can be retried once one is added.
 *   TO_SEND          WhatsApp, ready but not sent automatically (automatic
 *                    sending is not switched on): waiting for the School
 *                    Admin to send it from their phone (Send on WhatsApp).
 *                    Pending, not failed: nothing went wrong.
 *
 * Nothing is ever marked Sent or Delivered unless the email server or
 * WhatsApp said so.
 */
enum ResultDeliveryStatus: string
{
    case Pending = 'pending';
    case Sent = 'sent';
    case Delivered = 'delivered';
    case Failed = 'failed';
    case MissingContact = 'missing_contact';
    case ToSend = 'to_send';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Sent => 'Sent',
            self::Delivered => 'Delivered',
            self::Failed => 'Failed',
            self::MissingContact => 'Missing contact',
            self::ToSend => 'Pending (phone)',
        };
    }

    /** Tailwind classes for the status badge. */
    public function badge(): string
    {
        return match ($this) {
            self::Pending => 'bg-amber-100 text-amber-700 dark:bg-amber-900/30 dark:text-amber-300',
            self::Sent => 'bg-green-100 text-green-700 dark:bg-green-900/30 dark:text-green-400',
            self::Delivered => 'bg-emerald-100 text-emerald-800 dark:bg-emerald-900/30 dark:text-emerald-300',
            self::Failed => 'bg-red-100 text-red-700 dark:bg-red-900/30 dark:text-red-400',
            self::MissingContact => 'bg-gray-200 text-gray-700 dark:bg-gray-700 dark:text-gray-300',
            self::ToSend => 'bg-amber-100 text-amber-800 dark:bg-amber-900/30 dark:text-amber-300',
        };
    }

    /** Whether a retry should send it again. Never true for one that went. */
    public function isRetryable(): bool
    {
        return $this === self::Failed || $this === self::MissingContact || $this === self::ToSend;
    }

    public function wentOut(): bool
    {
        return $this === self::Sent || $this === self::Delivered;
    }
}
