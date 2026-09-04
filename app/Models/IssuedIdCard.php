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
     * Only the sample card uses this - see IdCardSample. Everything else
     * resolves its holder from the database as it always did.
     */
    private Student|Staff|null $resolvedHolder = null;

    /**
     * Hand this card a holder rather than making it find one.
     *
     * The template editor draws a specimen card from invented details, so
     * there is no pupil to look up. Without this the sample would have to be
     * a second, hand-maintained copy of the card design - which is what it
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

    public static function issueFor(School $school, IdCardHolderType $type, Student|Staff $holder, ?IdCardTemplate $template, User $issuedBy): self
    {
        return DB::transaction(function () use ($school, $type, $holder, $template, $issuedBy) {
            $existing = self::where('school_id', $school->id)
                ->where('holder_type', $type)
                ->where('holder_uuid', $holder->uuid)
                ->lockForUpdate()
                ->first();

            if ($existing) {
                if ($template && $existing->id_card_template_id !== $template->id) {
                    $existing->update(['id_card_template_id' => $template->id]);
                }

                return $existing;
            }

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

            return self::create([
                'school_id' => $school->id,
                'holder_type' => $type,
                'holder_uuid' => $holder->uuid,
                'id_card_template_id' => $template?->id,
                'card_number' => sprintf('%s-%s-%06d', $schoolPrefix, $typePrefix, $serial),
                'serial_number' => $serial,
                'status' => IssuedIdCardStatus::Active,
                'issued_by' => $issuedBy->id,
                'issued_at' => now(),
                'expiry_date' => self::expiryDateFor($school),
            ]);
        });
    }

    /**
     * A card is valid through the end of the school's current academic
     * session (assumed to run September-July, matching the convention
     * already used for examination sessions) - null if the school hasn't
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
