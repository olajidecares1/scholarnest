<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;

/**
 * One way a school may pay, as the ScholarNest Team has configured it.
 *
 * Named ...Setting rather than PaymentMethod because App\Enums\PaymentMethod
 * already holds the vocabulary - which methods exist at all - and a payment
 * records that enum, not this row. This table holds only what the team can
 * change: whether it is on, what it is called, and what a school is told.
 */
class PaymentMethodSetting extends Model
{
    use HasUuidRouteKey;

    protected $table = 'payment_methods';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'key',
        'label',
        'description',
        'is_enabled',
        'requires_receipt',
        'instructions',
        'details',
        'sort_order',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_enabled' => 'boolean',
            'requires_receipt' => 'boolean',
            'details' => 'array',
            'sort_order' => 'integer',
        ];
    }

    /**
     * @param  Builder<PaymentMethodSetting>  $query
     * @return Builder<PaymentMethodSetting>
     */
    public function scopeEnabled(Builder $query): Builder
    {
        return $query->where('is_enabled', true);
    }

    /**
     * @param  Builder<PaymentMethodSetting>  $query
     * @return Builder<PaymentMethodSetting>
     */
    public function scopeInDisplayOrder(Builder $query): Builder
    {
        return $query->orderBy('sort_order')->orderBy('label');
    }

    /**
     * The enum case this row configures, if it is one this build knows.
     *
     * A row whose key no longer matches the enum - a method removed from the
     * code but still in the table - returns null rather than throwing, so an
     * old row cannot take the payment page down.
     */
    public function method(): ?PaymentMethod
    {
        return PaymentMethod::tryFrom($this->key);
    }

    /**
     * One detail field, for the page that shows them.
     */
    public function detail(string $field): ?string
    {
        $value = $this->details[$field] ?? null;

        return filled($value) ? (string) $value : null;
    }

    /**
     * The detail fields a school needs, in the order they are read out.
     *
     * Empty ones are dropped rather than printed blank: a row reading
     * "Account Number —" on a payment page is worse than no row, because
     * somebody will transfer money anyway and guess.
     *
     * @return array<string, string>
     */
    public function bankFields(): array
    {
        $labels = [
            'bank_name' => 'Bank Name',
            'account_name' => 'Account Name',
            'account_number' => 'Account Number',
            'sort_code' => 'Sort Code',
        ];

        $fields = [];

        foreach ($labels as $field => $label) {
            if ($value = $this->detail($field)) {
                $fields[$label] = $value;
            }
        }

        return $fields;
    }

    /**
     * Does this method take payment into a bank account?
     *
     * Decides whether the Payment Settings form offers bank fields at all.
     * See App\Enums\PaymentMethod::usesBankAccount().
     */
    public function usesBankAccount(): bool
    {
        return $this->method()?->usesBankAccount() ?? false;
    }

    /**
     * Is this still the placeholder that shipped with the migration?
     *
     * The seeded values were the ones hard-coded into the template, kept so
     * nothing broke on the day they moved into the database. They are not
     * anybody's real account, and the Payment Settings page says so until they
     * are replaced - money transferred to a placeholder does not come back.
     */
    public function usesShippedPlaceholder(): bool
    {
        return $this->key === 'bank_transfer'
            && $this->detail('account_number') === '0123456789';
    }
}
