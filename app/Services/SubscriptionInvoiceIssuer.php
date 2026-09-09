<?php

namespace App\Services;

use App\Models\School;
use App\Models\Subscription;
use App\Models\SubscriptionInvoice;
use App\Models\SubscriptionTopUp;
use Illuminate\Database\QueryException;
use Illuminate\Support\Facades\DB;

/**
 * Raising AkademicNest's invoice to a school.
 *
 * One place, because an invoice is raised from two quite different events -
 * a school subscribing, and a school buying more student licences - and the
 * numbering has to be continuous across both. Two call sites each minting
 * their own number is how a business ends up with two invoice 41s.
 */
class SubscriptionInvoiceIssuer
{
    /**
     * How many times to retry a number that lost a race.
     *
     * The number is derived from the highest one already issued, so two
     * requests arriving together can derive the same one. The unique index on
     * `number` is what actually prevents a duplicate; this is what turns that
     * collision into a second attempt rather than a 500 on a school's
     * checkout.
     */
    private const NUMBER_ATTEMPTS = 5;

    /**
     * The invoice for a subscription a school has just submitted.
     *
     * Idempotent. A retried request, a double-clicked button or a replayed
     * job finds the invoice already raised and returns it, rather than
     * billing the school twice for one subscription.
     */
    public function forSubscription(Subscription $subscription): SubscriptionInvoice
    {
        $existing = SubscriptionInvoice::where('subscription_id', $subscription->id)->first();

        if ($existing) {
            return $existing;
        }

        $school = $subscription->school;
        $plan = $subscription->plan;
        $licences = $subscription->students_count;

        return $this->create($school, [
            'subscription_id' => $subscription->id,
            'plan_name' => $plan->name,
            'billing_cycle' => $subscription->billing_cycle?->label(),
            'description' => $licences
                ? "{$plan->name} plan - {$licences} student licence(s)"
                : "{$plan->name} plan subscription",
            'licences' => $licences,

            // Derived from what was actually charged rather than read off the
            // plan, so an invoice always adds up to the amount on it even if
            // the plan's price has since been edited.
            'unit_price' => $licences ? round((float) $subscription->amount / $licences, 2) : null,
            'currency' => $subscription->currency ?: 'NGN',
            'subtotal' => $subscription->amount,
            'total' => $subscription->amount,
            'payment_reference' => $subscription->reference,
        ]);
    }

    /**
     * The invoice for a student-licence top-up.
     */
    public function forTopUp(SubscriptionTopUp $topUp): SubscriptionInvoice
    {
        $existing = SubscriptionInvoice::where('subscription_top_up_id', $topUp->id)->first();

        if ($existing) {
            return $existing;
        }

        $subscription = $topUp->subscription;
        $school = $subscription->school;
        $licences = (int) $topUp->additional_students_count;

        return $this->create($school, [
            'subscription_id' => $subscription->id,
            'subscription_top_up_id' => $topUp->id,
            'plan_name' => $subscription->plan->name,
            'billing_cycle' => $subscription->billing_cycle?->label(),
            'description' => "Additional student licences - {$licences} licence(s)",
            'licences' => $licences,
            'unit_price' => $topUp->price_per_student,
            'currency' => $topUp->currency ?: 'NGN',
            'subtotal' => $topUp->additional_amount,
            'total' => $topUp->additional_amount,
            'payment_reference' => $topUp->reference,
        ]);
    }

    /**
     * @param  array<string, mixed>  $attributes
     */
    private function create(School $school, array $attributes): SubscriptionInvoice
    {
        $attributes = [
            ...$attributes,
            'school_id' => $school->id,
            'issued_at' => now(),

            // Copied, not joined. A school that renames itself or changes its
            // billing address must not rewrite the invoices it has already
            // been sent.
            'billed_to_name' => $school->name,
            'billed_to_email' => $school->billing_email,
            'billed_to_phone' => $school->billing_phone,
        ];

        for ($attempt = 1; $attempt <= self::NUMBER_ATTEMPTS; $attempt++) {
            try {
                return SubscriptionInvoice::create([
                    ...$attributes,
                    'number' => $this->nextNumber(),
                ]);
            } catch (QueryException $e) {
                if ($attempt === self::NUMBER_ATTEMPTS || ! $this->isDuplicateNumber($e)) {
                    throw $e;
                }
            }
        }

        // Unreachable: the loop either returns or rethrows.
        throw new \RuntimeException('Could not allocate an invoice number.');
    }

    /**
     * The next number in this year's sequence: SN-2026-000001.
     *
     * Restarts each year, which is how invoice sequences are normally read,
     * and the year in the number makes an invoice self-dating even when it is
     * quoted on its own in an email.
     */
    private function nextNumber(): string
    {
        $year = now()->format('Y');
        $prefix = "SN-{$year}-";

        $highest = SubscriptionInvoice::query()
            ->where('number', 'like', $prefix.'%')
            ->lockForUpdate()
            ->orderByDesc('number')
            ->value('number');

        $sequence = $highest ? ((int) substr($highest, strlen($prefix))) + 1 : 1;

        return $prefix.str_pad((string) $sequence, 6, '0', STR_PAD_LEFT);
    }

    private function isDuplicateNumber(QueryException $e): bool
    {
        // 23000/23505 are the SQLSTATE classes for an integrity constraint
        // violation across MySQL, SQLite and Postgres alike.
        return in_array($e->getCode(), ['23000', '23505'], true);
    }

    /**
     * Run the numbering inside a transaction where the driver supports the
     * row lock it takes. Callers that already have one get theirs reused.
     */
    public function issueForSubscription(Subscription $subscription): SubscriptionInvoice
    {
        return DB::transaction(fn () => $this->forSubscription($subscription));
    }

    public function issueForTopUp(SubscriptionTopUp $topUp): SubscriptionInvoice
    {
        return DB::transaction(fn () => $this->forTopUp($topUp));
    }
}
