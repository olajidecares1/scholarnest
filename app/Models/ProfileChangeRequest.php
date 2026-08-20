<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ProfileChangeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;

class ProfileChangeRequest extends Model
{
    /** @use HasFactory<ProfileChangeRequestFactory> */
    use HasFactory, HasUuidRouteKey;

    protected $fillable = [
        'school_id', 'requester_type', 'requester_uuid', 'subject_type', 'subject_uuid',
        'field_key', 'field_label', 'current_value', 'requested_value', 'reason',
        'status', 'reviewed_by', 'reviewed_at', 'review_note',
    ];

    protected function casts(): array
    {
        return [
            'reviewed_at' => 'datetime',
        ];
    }

    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    public function reviewedBy(): BelongsTo
    {
        return $this->belongsTo(User::class, 'reviewed_by');
    }

    public function subject(): Student|Staff|null
    {
        $query = match ($this->subject_type) {
            'student' => Student::where('uuid', $this->subject_uuid),
            'staff' => Staff::where('uuid', $this->subject_uuid),
            default => null,
        };

        return $query?->where('school_id', $this->school_id)->first();
    }

    /**
     * Applies the requested value to the real Student/Staff row and marks
     * the request approved, in one transaction - this *is* the sync, there
     * is no separate propagation step since every surface (profile, ID
     * card, report card, portals) already reads the live row.
     */
    public function approve(User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($reviewer, $note) {
            $subject = $this->subject();

            if ($subject) {
                $subject->update([$this->field_key => $this->requested_value]);
            }

            $this->update([
                'status' => 'approved',
                'reviewed_by' => $reviewer->id,
                'reviewed_at' => now(),
                'review_note' => $note,
            ]);
        });
    }

    public function reject(User $reviewer, ?string $note = null): void
    {
        $this->update([
            'status' => 'rejected',
            'reviewed_by' => $reviewer->id,
            'reviewed_at' => now(),
            'review_note' => $note,
        ]);
    }
}
