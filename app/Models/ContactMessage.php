<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use App\Support\SubmissionTopic;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * An enquiry sent from a school's public website.
 */
class ContactMessage extends Model
{
    use HasUuidRouteKey;

    protected $fillable = [
        'school_id',
        'name',
        'email',
        'phone',
        'subject',
        'message',
        'read_at',
        'read_by',
    ];

    protected function casts(): array
    {
        return [
            'read_at' => 'datetime',
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
     * @return BelongsTo<User, $this>
     */
    public function reader(): BelongsTo
    {
        return $this->belongsTo(User::class, 'read_by');
    }

    /**
     * What the inbox lists this under. The subject when there is one, and a
     * summary drawn from the message when there is not, the field is optional
     * on the public form. See [App\Support\SubmissionTopic].
     */
    public function topic(): string
    {
        return SubmissionTopic::from($this->subject, $this->message);
    }

    public function isUnread(): bool
    {
        return $this->read_at === null;
    }
}
