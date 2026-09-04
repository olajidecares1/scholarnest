<?php

namespace App\Models;

use App\Enums\PaymentStatus;
use App\Enums\SubscriptionTopUpStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\SubscriptionInvoiceFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;

/**
 * One invoice from ScholarNest to a school.
 *
 * NOT App\Models\Invoice, which is a school billing a parent for school fees.
 * Same word, different business - see the migration for why they are kept
 * apart.
 *
 * The row is a SNAPSHOT and is never edited after it is issued. Everything
 * that could later move - the plan's price, the school's name, its billing
 * email - is copied in at issue time, so re-reading an invoice a year later
 * shows what the school was actually charged rather than what today's prices
 * would make of it.
 *
 * Payment status is the one thing NOT stored here, and deliberately: it is
 * read from the payment record this invoice was raised against. Storing it
 * too would mean two answers to "has this been paid", and they would drift the
 * first time a Super Admin approved a payment.
 */
class SubscriptionInvoice extends Model
{
    /** @use HasFactory<SubscriptionInvoiceFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'number',
        'school_id',
        'subscription_id',
        'subscription_top_up_id',
        'issued_at',
        'billed_to_name',
        'billed_to_email',
        'billed_to_phone',
        'plan_name',
        'billing_cycle',
        'description',
        'licences',
        'unit_price',
        'currency',
        'subtotal',
        'discount',
        'fees',
        'total',
        'payment_reference',
        'notes',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'issued_at' => 'datetime',
            'licences' => 'integer',
            'unit_price' => 'decimal:2',
            'subtotal' => 'decimal:2',
            'discount' => 'decimal:2',
            'fees' => 'decimal:2',
            'total' => 'decimal:2',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<SubscriptionTopUp, $this>
     */
    public function topUp(): BelongsTo
    {
        return $this->belongsTo(SubscriptionTopUp::class, 'subscription_top_up_id');
    }

    /**
     * The payment this invoice was raised against, if there is one.
     *
     * A top-up carries its receipt on the top-up row itself rather than in a
     * Payment, which is why this can be null on a perfectly ordinary invoice.
     */
    public function payment(): ?Payment
    {
        return $this->subscription?->latestPayment;
    }

    /**
     * Paid, pending review, or refused - read from the payment, never stored.
     */
    public function paymentStatus(): string
    {
        if ($this->subscription_top_up_id) {
            return match ($this->topUp?->status) {
                SubscriptionTopUpStatus::Approved => 'Paid',
                SubscriptionTopUpStatus::Rejected => 'Rejected',
                default => 'Awaiting approval',
            };
        }

        return match ($this->payment()?->status) {
            PaymentStatus::Verified => 'Paid',
            PaymentStatus::Rejected => 'Rejected',
            default => 'Awaiting approval',
        };
    }

    public function isPaid(): bool
    {
        return $this->paymentStatus() === 'Paid';
    }

    /**
     * When a Super Admin approved the payment, if one has.
     */
    public function approvedAt(): ?Carbon
    {
        return $this->subscription_top_up_id
            ? $this->topUp?->verified_at
            : $this->payment()?->verified_at;
    }

    /**
     * Which Super Admin approved it. Named, because "approved" with nobody
     * attached is not an audit trail.
     */
    public function approvedBy(): ?User
    {
        return $this->subscription_top_up_id
            ? $this->topUp?->verifiedBy
            : $this->payment()?->verifiedBy;
    }

    public function statusBadgeClasses(): string
    {
        return match ($this->paymentStatus()) {
            'Paid' => 'bg-green-100 text-green-700',
            'Rejected' => 'bg-red-100 text-red-700',
            default => 'bg-amber-100 text-amber-700',
        };
    }

    /**
     * The amount, written the way it is printed.
     */
    public function formattedTotal(): string
    {
        return $this->currency.' '.number_format((float) $this->total, 2);
    }
}
