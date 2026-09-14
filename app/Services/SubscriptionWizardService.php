<?php

namespace App\Services;

use App\Enums\BillingCycle;
use App\Models\School;
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

        $reference = strtoupper(Str::slug($school->name, '')).'-'.now()->format('dmy').'-'.strtoupper(Str::random(4));

        $this->put(['reference' => $reference]);

        return $reference;
    }

    public function clear(): void
    {
        session()->forget(self::SESSION_KEY);
    }
}
