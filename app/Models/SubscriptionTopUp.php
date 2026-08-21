<?php

namespace App\Models;

use App\Enums\PaymentMethod;
use App\Enums\SubscriptionTopUpStatus;
use App\Support\HasUuidRouteKey;
use Database\Factories\SubscriptionTopUpFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class SubscriptionTopUp extends Model
{
    /** @use HasFactory<SubscriptionTopUpFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'subscription_id',
        'additional_students_count',
        'additional_amount',
        'price_per_student',
        'approved_students_count',
        'previous_students_count',
        'new_students_count',
        'currency',
        'payment_method',
        'receipt_path',
        'receipt_original_name',
        'status',
        'reference',
        'verified_by',
        'verified_at',
        'notes',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'additional_students_count' => 'integer',
            'additional_amount' => 'decimal:2',
            'price_per_student' => 'decimal:2',
            'approved_students_count' => 'integer',
            'previous_students_count' => 'integer',
            'new_students_count' => 'integer',
            'payment_method' => PaymentMethod::class,
            'status' => SubscriptionTopUpStatus::class,
            'verified_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<Subscription, $this>
     */
    public function subscription(): BelongsTo
    {
        return $this->belongsTo(Subscription::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function verifiedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'verified_by');
    }
}
