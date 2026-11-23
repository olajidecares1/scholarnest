<?php

namespace App\Models;

use App\Enums\ResultDeliveryChannel;
use App\Enums\ResultDeliveryStatus;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One message carrying one student's result details to one parent/guardian
 * on one channel. See the migration for what it is for, and
 * App\Services\ResultDetails\ResultDetailsSender for how rows move.
 */
class ResultDetailDelivery extends Model
{
    use HasUuidRouteKey;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'batch',
        'student_id',
        'result_checking_pin_id',
        'examination_id',
        'channel',
        'recipient',
        'recipient_name',
        'status',
        'error',
        'provider_message_id',
        'attempts',
        'sent_by',
        'sent_at',
        'delivered_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'channel' => ResultDeliveryChannel::class,
            'status' => ResultDeliveryStatus::class,
            'attempts' => 'integer',
            'sent_at' => 'datetime',
            'delivered_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<ResultCheckingPin, $this>
     */
    public function token(): BelongsTo
    {
        return $this->belongsTo(ResultCheckingPin::class, 'result_checking_pin_id');
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function sender(): BelongsTo
    {
        return $this->belongsTo(User::class, 'sent_by');
    }

    /**
     * @param  Builder<ResultDetailDelivery>  $query
     * @return Builder<ResultDetailDelivery>
     */
    public function scopeForSchool(Builder $query, School $school): Builder
    {
        return $query->where('school_id', $school->id);
    }
}
