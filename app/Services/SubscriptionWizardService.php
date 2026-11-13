<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Enums\SubscriptionStatus;
use App\Models\School;
use App\Models\Subscription;
use Illuminate\Support\Str;

class SubscriptionWizardService
{
    private const SESSION_KEY = 'subscription_wizard';

    /**
     * @return array<string, mixed>
     */
    public function data(): array
    {
        return session(self::SESSION_KEY, []);
    }

    public function get(string $key, mixed $default = null): mixed
    {
        return $this->data()[$key] ?? $default;
    }

    /**
     * @param  array<string, mixed>  $values
     */
    public function put(array $values): void
    {
        session([self::SESSION_KEY => array_merge($this->data(), $values)]);
    }

    public function hasChosenPlan(): bool
    {
        return $this->get('plan_id') !== null;
    }

    /**
     * Whether the price is a per-student one, which is what decides both the
     * amount and the school's capacity for the term.
     *
     * Asked in more than one place, the progress bar draws a step for it, the
     * screens after it link back to that step, so it is answered here once
     * rather than by each caller comparing the billing cycle for itself.
     */
    public function isPerStudent(): bool
    {
        return $this->get('billing_cycle') === BillingCycle::PerStudentPerTerm->value;
    }

    public function hasStudentCapacity(): bool
    {
        return $this->get('students_count') !== null;
    }

    public function hasBillingDetails(): bool
    {
        return $this->get('billing_contact_name') !== null;
    }

    public function hasPaymentMethod(): bool
    {
        return $this->get('payment_method') !== null;
    }

    public function reference(School $school): string
    {
        if ($reference = $this->get('reference')) {
            return $reference;
        }

        // The school's name is cut to 20 letters: the reference is quoted in a
        // bank narration and kept in a 255-character unique column, and a long
        // school name used to overflow it, failing the final step with a
        // database error after the receipt had been uploaded.
        $stem = substr(strtoupper(Str::slug($school->name, '')), 0, 20) ?: 'SCHOOL';
        $reference = $stem.'-'.now()->format('dmy').'-'.strtoupper(Str::random(4));

        $this->put(['reference' => $reference]);

        return $reference;
    }

    /**
     * Everything the final step needs, present and of the right shape.
     *
     * A session that lost part of the wizard (it expired, or a step was
     * opened in another tab) must be sent back to fill it in again, not
     * reach the database with a missing plan or amount.
     */
    public function isComplete(): bool
    {
        $data = $this->data();

        foreach (['plan_id', 'billing_cycle', 'amount', 'billing_contact_name', 'payment_method', 'receipt_path'] as $key) {
            if (blank($data[$key] ?? null)) {
                return false;
            }
        }

        if (BillingCycle::tryFrom((string) $data['billing_cycle']) === null || ! is_numeric($data['amount']) || (float) $data['amount'] <= 0) {
            return false;
        }

        return ! $this->isPerStudent() || (int) ($data['students_count'] ?? 0) > 0;
    }

    /**
     * The school's subscription that is still waiting on the AkademicNest
     * Team, if there is one. A second application while one is under review
     * is a duplicate, whatever caused it: a double click, a refresh, a second
     * tab, or a school that met an error page and went through again.
     */
    public function pendingSubscription(School $school): ?Subscription
    {
        return $school->subscriptions()
            ->whereIn('status', [SubscriptionStatus::PendingVerification, SubscriptionStatus::PendingPayment])
            ->latest('id')
            ->first();
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
