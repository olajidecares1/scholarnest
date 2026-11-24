<?php

namespace App\Models;

use App\Services\IdentifierGenerator;
use App\Support\HasUuidRouteKey;
use Database\Factories\ProfileChangeRequestFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;
use Illuminate\Validation\ValidationException;

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
     * the request approved, in one transaction, this *is* the sync, there
     * is no separate propagation step since every surface (profile, ID
     * card, report card, portals) already reads the live row.
     */
    /**
     * @throws ValidationException when the requested admission number or
     *                             Staff ID already belongs to someone else
     *                             at the school.
     */
    public function approve(User $reviewer, ?string $note = null): void
    {
        DB::transaction(function () use ($reviewer, $note) {
            $subject = $this->subject();

            if ($subject && in_array($this->field_key, ['admission_number', 'staff_number'], true)) {
                $this->applyIdentifierCorrection($subject, $reviewer);
            } elseif ($subject) {
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

    /**
     * An admission number or Staff ID is the person's permanent identity, so
     * the model only lets it change inside a deliberate correction. It must
     * still be unique at the school (whatever its capital letters), and the
     * old number is kept on record so anything printed under it stays
     * traceable.
     */
    private function applyIdentifierCorrection(Student|Staff $subject, User $reviewer): void
    {
        $column = $this->field_key;
        $new = trim((string) $this->requested_value);
        $old = (string) $subject->{$column};

        if ($new === '' || $new === $old) {
            return;
        }

        $taken = $subject::query()
            ->where('school_id', $subject->school_id)
            ->whereKeyNot($subject->getKey())
            ->whereRaw("LOWER({$column}) = ?", [mb_strtolower($new)])
            ->exists();

        if ($taken) {
            throw ValidationException::withMessages([
                'review_note' => "{$new} already belongs to someone else at your school, so it cannot be given to {$subject->fullName()}.",
            ]);
        }

        $apply = fn () => $subject->update([$column => $new]);

        $subject instanceof Student
            ? Student::correctingAdmissionNumber($apply)
            : Staff::correctingStaffId($apply);

        IdentifierChange::create([
            'school_id' => $subject->school_id,
            'type' => $subject instanceof Student ? IdentifierGenerator::ADMISSION : IdentifierGenerator::STAFF,
            'holder_id' => $subject->getKey(),
            'old_value' => $old,
            'new_value' => $new,
            'batch' => (string) Str::uuid(),
            'changed_by' => $reviewer->id,
        ]);
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
