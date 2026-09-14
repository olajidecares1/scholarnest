<?php

namespace App\Services;

use App\Models\PaymentMethodSetting;
use Illuminate\Support\Collection;

/**
 * Which ways of paying are open right now.
 *
 * One place, asked by both the page that offers the choice and the request
 * that accepts it. That is the whole point: the brief is explicit that a
 * disabled method must be refused by the server and not merely dropped from
 * the page, and a rule enforced in two places is a rule that will eventually
 * be enforced in one.
 *
 * A method the code no longer knows about, a row left behind after a method
 * was removed, is dropped here rather than allowed to reach a controller that
 * would have to guess what to do with it.
 */
class AvailablePaymentMethods
{
    /**
     * Everything a school may currently choose, in display order.
     *
     * @return Collection<int, PaymentMethodSetting>
     */
    public function all(): Collection
    {
        return PaymentMethodSetting::query()
            ->enabled()
            ->inDisplayOrder()
            ->get()
            ->filter(fn (PaymentMethodSetting $setting) => $setting->method() !== null)
            ->values();
    }

    /**
     * Is this one open?
     *
     * Takes the raw string a request submitted, because that is what has to be
     * checked, a key that is not a method at all must answer false rather
     * than blow up on the way to finding out.
     */
    public function allows(?string $key): bool
    {
        return $key !== null && $this->all()->contains(fn (PaymentMethodSetting $setting) => $setting->key === $key);
    }

    /**
     * The keys, for a validation rule.
     *
     * @return list<string>
     */
    public function keys(): array
    {
        return $this->all()->pluck('key')->all();
    }

    /**
     * Nothing enabled at all.
     *
     * Worth asking directly. The page has to say so plainly rather than render
     * an empty list of radio buttons and a submit button that cannot succeed.
     */
    public function noneAvailable(): bool
    {
        return $this->all()->isEmpty();
    }

    /**
     * The one a school picked, if it is open.
     */
    public function find(?string $key): ?PaymentMethodSetting
    {
        return $this->all()->firstWhere('key', $key);
    }
}
