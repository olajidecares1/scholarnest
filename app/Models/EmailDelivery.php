<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;

/**
 * One transactional email, and whether the mail server accepted it.
 *
 * Written by App\Services\Mail\TransactionalMailer for every email it sends,
 * so "was the welcome email sent?" has an answer, with the reason when it was
 * not, instead of a guess.
 */
class EmailDelivery extends Model
{
    public const SENT = 'sent';

    public const FAILED = 'failed';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'kind',
        'about_type',
        'about_id',
        'recipient',
        'subject',
        'status',
        'error',
        'sent_at',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'sent_at' => 'datetime',
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
     * @return MorphTo<Model, $this>
     */
    public function about(): MorphTo
    {
        return $this->morphTo();
    }

    public function wasSent(): bool
    {
        return $this->status === self::SENT;
    }
}
