<?php

use App\Enums\CustomDomainStatus;
use App\Models\CustomDomain;
use App\Models\School;
use App\Services\CustomDomainVerificationService;

/**
 * The rename reached the DNS record schools are asked to create.
 *
 * That record lives at the SCHOOL's registrar, on a domain this platform does
 * not control, so renaming the prefix alone would quietly un-verify every
 * domain verified under an older name - a school would find its site off, with
 * nothing in the application to say why. The new prefix is what anybody is
 * told to create; the old ones are still accepted and never advertised.
 */
class RecordedDnsVerificationService extends CustomDomainVerificationService
{
    /** @var list<string> */
    public array $asked = [];

    /** @param  array<string, list<array<string, mixed>>>  $answers */
    public function __construct(private array $answers = []) {}

    /** @return list<array<string, mixed>> */
    protected function lookupRecords(string $host, int $type): array
    {
        if ($type === DNS_TXT) {
            $this->asked[] = $host;
        }

        return $this->answers[$host] ?? [];
    }
}

function domainAwaitingVerification(): CustomDomain
{
    return CustomDomain::factory()->create([
        'school_id' => School::factory()->create()->id,
        'domain' => 'greenfield.edu.ng',
        'status' => CustomDomainStatus::PendingVerification,
        'verification_token' => 'the-real-token',
    ]);
}

test('the record a school is told to create carries the current name', function () {
    expect(domainAwaitingVerification()->verificationRecordHost())
        ->toBe('_akademicnest-verify.greenfield.edu.ng');
});

test('the wizard shows the current prefix and none of the old ones', function () {
    $domain = domainAwaitingVerification();

    expect($domain->legacyVerificationRecordHosts())
        ->toContain('_edunest-verify.greenfield.edu.ng')
        ->and($domain->verificationRecordHost())
        ->not->toContain('edunest')
        ->and($domain->verificationRecordHost())
        ->not->toContain('scholarnest');
});

test('the current prefix is asked first, and alone when it answers', function () {
    $domain = domainAwaitingVerification();

    $service = new RecordedDnsVerificationService([
        '_akademicnest-verify.greenfield.edu.ng' => [['txt' => 'the-real-token']],
    ]);

    $service->verify($domain);

    expect($service->asked)->toBe(['_akademicnest-verify.greenfield.edu.ng']);
});

test('a domain verified under the old name is not un-verified by the rename', function () {
    $domain = domainAwaitingVerification();

    // Only the pre-rename record exists, because that is what this school was
    // told to create back when it set the domain up. Nothing in its DNS has
    // changed; the platform's name did.
    $service = new RecordedDnsVerificationService([
        '_edunest-verify.greenfield.edu.ng' => [['txt' => 'the-real-token']],
    ]);

    $service->verify($domain);

    expect($service->asked)->toContain('_edunest-verify.greenfield.edu.ng')
        ->and($domain->fresh()->last_check_error)
        ->not->toContain('TXT record');
});

test('a wrong token fails under every prefix, old ones included', function () {
    $domain = domainAwaitingVerification();

    $service = new RecordedDnsVerificationService([
        '_akademicnest-verify.greenfield.edu.ng' => [['txt' => 'somebody-elses-token']],
        '_edunest-verify.greenfield.edu.ng' => [['txt' => 'somebody-elses-token']],
    ]);

    expect($service->verify($domain))->toBeFalse()
        ->and($domain->fresh()->status)->toBe(CustomDomainStatus::Failed);
});
