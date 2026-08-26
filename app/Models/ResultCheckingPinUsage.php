<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ResultCheckingPinUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

class ResultCheckingPinUsage extends Model
{
    /** @use HasFactory<ResultCheckingPinUsageFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'result_checking_pin_id',
        'student_id',
        'examination_id',
        'ip_address',
        'used_at',
        'redeemed_by_type',
        'redeemed_by_id',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ResultCheckingPin, $this>
     */
    public function pin(): BelongsTo
    {
        return $this->belongsTo(ResultCheckingPin::class, 'result_checking_pin_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * The signed-in guardian or student who redeemed this token, or null when
     * it was redeemed from the public result page, where nobody is signed in.
     *
     * @return MorphTo<Model, $this>
     */
    public function redeemedBy(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * How to describe the redeemer on the school's tracking screen.
     */
    public function redeemedByLabel(): string
    {
        $redeemer = $this->redeemedBy;

        return match (true) {
            $redeemer instanceof Guardian => $redeemer->name.' (parent/guardian)',
            $redeemer instanceof Student => $redeemer->fullName().' (student)',

            // Not "unknown": nobody was signed in, because the public result
            // page does not ask anyone to be. The token was the credential.
            default => 'Result link (no sign-in)',
        };
    }
}
