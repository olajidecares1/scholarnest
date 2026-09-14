<?php

namespace App\Models;

use App\Enums\CustomDomainSslStatus;
use App\Enums\CustomDomainStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\CustomDomainFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class CustomDomain extends Model
{
    /** @use HasFactory<CustomDomainFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'domain',
        'is_primary',
        'status',
        'verification_token',
        'verified_at',
        'ssl_status',
        'ssl_issued_at',
        'redirect_default_domain',
        'last_checked_at',
        'last_check_error',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_primary' => 'boolean',
            'status' => CustomDomainStatus::class,
            'verified_at' => 'datetime',
            'ssl_status' => CustomDomainSslStatus::class,
            'ssl_issued_at' => 'datetime',
            'redirect_default_domain' => 'boolean',
            'last_checked_at' => 'datetime',
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
     * The DNS TXT record host the school must add to prove ownership, scoped
     * under a fixed prefix so it never collides with the domain's other
     * records, and works identically for root domains and subdomains.
     */
    public function verificationRecordHost(): string
    {
        return config('custom_domain.txt_verification_prefix').".{$this->domain}";
    }

    /**
     * The same host under the prefixes this platform used before it was
     * renamed. Checked during verification, never shown to a school: a domain
     * verified under the old name must not fall over because the brand
     * changed, but nobody new should be told to create one.
     *
     * @return list<string>
     */
    public function legacyVerificationRecordHosts(): array
    {
        return collect(config('custom_domain.legacy_txt_verification_prefixes', []))
            ->map(fn (string $prefix) => "{$prefix}.{$this->domain}")
            ->reject(fn (string $host) => $host === $this->verificationRecordHost())
            ->values()
            ->all();
    }

    public function isVerifiedAndActive(): bool
    {
        return $this->status === CustomDomainStatus::Verified;
    }

    /**
     * The 5-step setup wizard's progress, computed from this domain's actual
     * state rather than tracked separately, so it can never drift out of
     * sync with what's really happened.
     *
     * @return list<array{label: string, state: 'done'|'current'|'upcoming'}>
     */
    public function wizardSteps(): array
    {
        $labels = ['Save Domain', 'Configure DNS', 'Verify Domain', 'Install SSL Certificate', 'Connection Complete'];

        $currentIndex = match (true) {
            $this->ssl_status === CustomDomainSslStatus::Active => 4,
            $this->status === CustomDomainStatus::Verified => 3,
            $this->last_checked_at !== null => 2,
            default => 1,
        };

        return collect($labels)
            ->map(fn (string $label, int $index) => [
                'label' => $label,
                'state' => match (true) {
                    $index < $currentIndex => 'done',
                    $index === $currentIndex => 'current',
                    default => 'upcoming',
                },
            ])
            ->values()
            ->all();
    }
}
