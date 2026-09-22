<?php

namespace App\Services\Attendance;

use Illuminate\Support\Carbon;

/**
 * One scan as the PHONE saw it.
 *
 * Everything here is the device's account of what happened, which is the whole
 * point: a phone with no signal at the gate still knows the time, its own
 * position and an id for the scan, and can hand all three over hours later.
 * Nothing in here is trusted as given, see CheckInService for the clamping.
 */
final readonly class ScannedPunch
{
    public function __construct(
        public string $clientUuid,
        public Carbon $scannedAt,
        public ?float $latitude = null,
        public ?float $longitude = null,
        public ?int $accuracyMetres = null,
        public bool $wasQueued = false,
    ) {}

    /**
     * @param  array<string, mixed>  $payload  Already validated by the controller.
     */
    public static function fromArray(array $payload): self
    {
        return new self(
            clientUuid: (string) $payload['client_uuid'],
            scannedAt: Carbon::parse($payload['scanned_at'])->utc(),
            latitude: isset($payload['latitude']) ? (float) $payload['latitude'] : null,
            longitude: isset($payload['longitude']) ? (float) $payload['longitude'] : null,
            accuracyMetres: isset($payload['accuracy_metres']) ? (int) $payload['accuracy_metres'] : null,
            wasQueued: (bool) ($payload['was_queued'] ?? false),
        );
    }
}
