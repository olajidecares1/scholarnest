<?php

namespace App\Models;

use App\Enums\IdCardHolderType;
use App\Enums\IssuedIdCardStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\IssuedIdCardFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Carbon;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class IssuedIdCard extends Model
{
    /** @use HasFactory<IssuedIdCardFactory> */
    use HasFactory, HasUuidRouteKey;

    protected $fillable = [
        'school_id', 'holder_type', 'holder_uuid', 'id_card_template_id',
        'card_number', 'serial_number', 'status', 'issued_by', 'issued_at', 'expiry_date', 'revoked_at',
    ];

    protected function casts(): array
    {
        return [
            'holder_type' => IdCardHolderType::class,
            'status' => IssuedIdCardStatus::class,
            'serial_number' => 'integer',
            'issued_at' => 'datetime',
            'expiry_date' => 'date',
            'revoked_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function template(): BelongsTo
    {
        return $this->belongsTo(IdCardTemplate::class, 'id_card_template_id');
    }

    public function issuedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'issued_by');
    }

    public static function resolveHolder(IdCardHolderType $type, string $uuid, int $schoolId): Student|Staff|null
    {
        $query = match ($type) {
            IdCardHolderType::Student => Student::where('uuid', $uuid),
            IdCardHolderType::TeachingStaff, IdCardHolderType::NonTeachingStaff => Staff::where('uuid', $uuid),
        };

        return $query->where('school_id', $schoolId)->first();
    }

    /**
     * A holder supplied directly, instead of looked up.
     *
     * Only the sample card uses this, see IdCardSample. Everything else
     * resolves its holder from the database as it always did.
     */
    private Student|Staff|null $resolvedHolder = null;

    /**
     * Hand this card a holder rather than making it find one.
     *
     * The template editor draws a specimen card from invented details, so
     * there is no pupil to look up. Without this the sample would have to be
     * a second, hand-maintained copy of the card design, which is what it
     * used to be, and why the preview stopped resembling the real thing.
     */
    public function withHolder(Student|Staff $holder): static
    {
        $this->resolvedHolder = $holder;

        return $this;
    }

    public function holder(): Student|Staff|null
    {
        return $this->resolvedHolder
            ?? self::resolveHolder($this->holder_type, $this->holder_uuid, $this->school_id);
    }

    public function verificationUrl(): string
    {
        return route('id-verify.show', $this);
    }

    /**
     * The one ID card record for this person, issued now if they have none.
     *
     * One record per person, ever (unique on school + holder in the database
     * too): generating again returns the same card, its number unchanged.
     * A staff member who moved between teaching and non-teaching keeps their
     * card, which takes their current type. A card that was revoked (lost,
     * or its owner deleted) is reissued: a new card number and a new QR
     * code, so the revoked card can never verify again.
     */
    public static function issueFor(School $school, IdCardHolderType $type, Student|Staff $holder, ?IdCardTemplate $template, User $issuedBy): self
    {
        return DB::transaction(function () use ($school, $type, $holder, $template, $issuedBy) {
            $existing = self::where('school_id', $school->id)
                ->where('holder_uuid', $holder->uuid)
                ->lockForUpdate()
                ->first();

            if ($existing && $existing->status === IssuedIdCardStatus::Revoked) {
                [$serial, $number] = self::nextNumber($school, $type);

                // forceFill: the uuid (the QR code's link) is not fillable.
                $existing->forceFill([
                    'uuid' => (string) Str::uuid(),
                    'holder_type' => $type,
                    'id_card_template_id' => $template?->id ?? $existing->id_card_template_id,
                    'card_number' => $number,
                    'serial_number' => $serial,
                    'status' => IssuedIdCardStatus::Active,
                    'issued_by' => $issuedBy->id,
                    'issued_at' => now(),
                    'expiry_date' => self::expiryDateFor($school),
                    'revoked_at' => null,
                ])->save();

                return $existing;
            }

            if ($existing) {
                $changes = [];

                if ($existing->holder_type !== $type) {
                    $changes['holder_type'] = $type;
                }

                if ($template && $existing->id_card_template_id !== $template->id) {
                    $changes['id_card_template_id'] = $template->id;
                }

                if ($changes !== []) {
                    $existing->update($changes);
                }

                return $existing;
            }

            [$serial, $number] = self::nextNumber($school, $type);

            return self::create([
                'school_id' => $school->id,
                'holder_type' => $type,
                'holder_uuid' => $holder->uuid,
                'id_card_template_id' => $template?->id,
                'card_number' => $number,
                'serial_number' => $serial,
                'status' => IssuedIdCardStatus::Active,
                'issued_by' => $issuedBy->id,
                'issued_at' => now(),
                'expiry_date' => self::expiryDateFor($school),
            ]);
        });
    }

    /**
     * Revoke the card of a person whose record is gone (Student and Staff
     * call this when deleted). The record stays, for the register.
     */
    public static function revokeFor(int $schoolId, ?string $holderUuid): void
    {
        if (! $holderUuid) {
            return;
        }

        self::where('school_id', $schoolId)
            ->where('holder_uuid', $holderUuid)
            ->where('status', IssuedIdCardStatus::Active)
            ->update(['status' => IssuedIdCardStatus::Revoked, 'revoked_at' => now()]);
    }

    /**
     * The next serial and card number for this school and card type, e.g.
     * GLOR-STU-000042. Card numbers are unique across the platform.
     *
     * @return array{0: int, 1: string}
     */
    private static function nextNumber(School $school, IdCardHolderType $type): array
    {
        $serial = (int) self::where('school_id', $school->id)
            ->where('holder_type', $type)
            ->lockForUpdate()
            ->max('serial_number') + 1;

        $schoolPrefix = Str::of($school->slug)->replace('-', '')->upper()->substr(0, 4);
        $typePrefix = match ($type) {
            IdCardHolderType::Student => 'STU',
            IdCardHolderType::TeachingStaff => 'TST',
            IdCardHolderType::NonTeachingStaff => 'NTS',
        };

        do {
            $number = sprintf('%s-%s-%06d', $schoolPrefix, $typePrefix, $serial);
            $taken = self::where('card_number', $number)->exists();
            $serial += $taken ? 1 : 0;
        } while ($taken);

        return [$serial, $number];
    }

    /**
     * A card is valid through the end of the school's current academic
     * session (assumed to run September-July, matching the convention
     * already used for examination sessions), null if the school hasn't
     * set one, rather than guessing at a date that might be wrong.
     */
    private static function expiryDateFor(School $school): ?Carbon
    {
        if (! $school->current_session || ! preg_match('/^\d{4}\/(\d{4})$/', $school->current_session, $matches)) {
            return null;
        }

        return Carbon::createFromDate((int) $matches[1], 7, 31);
    }
}
