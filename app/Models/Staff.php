<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Models\Concerns\HasProtectedPhoto;
use App\Models\Concerns\HasSignature;
use App\Services\DefaultClassSubjects;
use App\Services\IdentifierGenerator;
use App\Support\HasUuidRouteKey;
use Database\Factories\StaffFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class Staff extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    /** @use HasFactory<StaffFactory> */
    use Authenticatable, CanResetPassword, HasApiTokens, HasFactory, HasSignature, HasUuidRouteKey, Notifiable;

    use HasProtectedPhoto;

    /**
     * Class name => default-subject group, looked up once per request by
     * canEnterScoresFor().
     *
     * @var array<string, ?string>
     */
    private array $subjectGroups = [];

    /**
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

    /**
     * A staff member's Staff ID is their permanent identity at the school:
     * their ID card, attendance and sign-in all go by it. Once given, nothing
     * changes it by the way: editing their name, role, department or any
     * other detail leaves it as it is, and any change to it made through the
     * model is put back.
     *
     * The deliberate exceptions: a School Admin approving the staff member's
     * own request to correct it (correctingStaffId(), from
     * ProfileChangeRequest::approve, recorded in identifier_changes), and the
     * School Code renumbering (IdentifierRegenerator), which writes to the
     * table directly and keeps the old IDs traceable.
     *
     * A deleted record's Staff ID goes back in the pool and is given to the
     * next one registered; any ID card issued to them is revoked.
     */
    /**
     * Set only while correctingStaffId() runs.
     */
    private static bool $staffIdCorrection = false;

    /**
     * Run $callback with the Staff ID open to a deliberate change.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function correctingStaffId(callable $callback): mixed
    {
        self::$staffIdCorrection = true;

        try {
            return $callback();
        } finally {
            self::$staffIdCorrection = false;
        }
    }

    protected static function booted(): void
    {
        static::updating(function (self $record) {
            if ($record->isDirty('staff_number') && ! self::$staffIdCorrection) {
                $record->staff_number = $record->getOriginal('staff_number');
            }
        });

        static::deleted(function (self $record) {
            app(IdentifierGenerator::class)->release($record);
            IssuedIdCard::revokeFor($record->school_id, $record->uuid);
        });
    }

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'staff_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'role',
        'department',
        'qualification',
        'employment_date',
        'emergency_contact_name',
        'emergency_contact_phone',
        'address',
        'phone',
        'email',
        'photo_path',
        'blood_group',
        'password',
        'must_change_password',
        'is_active',
        'notes',
    ];

    /**
     * The attributes that should be hidden for serialization.
     *
     * @var list<string>
     */
    protected $hidden = [
        'password',
        'remember_token',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'gender' => Gender::class,
            'role' => StaffRole::class,
            'date_of_birth' => 'date',
            'employment_date' => 'date',
            'password' => 'hashed',
            'last_login_at' => 'datetime',
            'is_active' => 'boolean',
            'must_change_password' => 'boolean',
        ];
    }

    /**
     * This member of staff's own register: when they arrived and when they
     * left, one row per day. See App\Models\StaffAttendanceRecord.
     *
     * @return HasMany<StaffAttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(StaffAttendanceRecord::class);
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<CbtTest, $this>
     */
    public function cbtTests(): HasMany
    {
        return $this->hasMany(CbtTest::class);
    }

    /**
     * @return HasMany<TeacherAssignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(TeacherAssignment::class);
    }

    /**
     * Every class this teacher is the Class Teacher of, a teacher may hold
     * this role for more than one class.
     *
     * @return Collection<int, string>
     */
    public function classesAsClassTeacher(): Collection
    {
        return $this->assignments()
            ->where('type', TeacherAssignmentType::ClassTeacher)
            ->pluck('class_name');
    }

    /**
     * Every (class_name, subject) pair this teacher has been assigned to
     * teach as a Subject Teacher.
     *
     * @return Collection<int, array{class_name: string, subject: string}>
     */
    public function subjectAssignments(): Collection
    {
        return $this->assignments()
            ->where('type', TeacherAssignmentType::SubjectTeacher)
            ->get(['class_name', 'subject'])
            ->map(fn (TeacherAssignment $assignment) => ['class_name' => $assignment->class_name, 'subject' => $assignment->subject])
            ->values();
    }

    /**
     * Classes this member is the class teacher of.
     *
     * @return Collection<int, string>
     */
    public function classTeacherClassNames(): Collection
    {
        return $this->assignments()
            ->where('type', TeacherAssignmentType::ClassTeacher)
            ->pluck('class_name')
            ->unique()
            ->values();
    }

    /**
     * Every class whose results this member has any part in entering.
     *
     * @return Collection<int, string>
     */
    public function scorableClassNames(): Collection
    {
        return $this->subjectAssignments()
            ->pluck('class_name')
            ->merge($this->classTeacherClassNames())
            ->unique()
            ->values();
    }

    /**
     * May this member enter scores for this subject in this class?
     *
     * Two ways to qualify, and the second is the one that was missing. A
     * subject teacher may enter the subject they teach. A class teacher may
     * enter any subject in their own class, because compiling that class's
     * results is what being its class teacher means, they collect marks from
     * the subject teachers and enter the sheet.
     *
     * Without the class-teacher case, a teacher who taught no individual
     * subject, the ordinary arrangement in a primary class, and common in a
     * small secondary school, opened the score page to nothing at all, with
     * no indication that the cause was an assignment rather than an empty
     * term.
     */
    public function canEnterScoresFor(string $className, string $subject): bool
    {
        if ($this->classTeacherClassNames()->contains($className)) {
            return true;
        }

        // The subject the teacher was assigned and the one on the score
        // sheet are compared as subjects, not as strings: "Maths" assigned
        // is "Mathematics" on the sheet, "christian religious studies" is
        // "Christian Religious Studies". An exact-text comparison hid a
        // subject from the very teacher assigned to it.
        if (! array_key_exists($className, $this->subjectGroups)) {
            $this->subjectGroups[$className] = DefaultClassSubjects::groupForSchoolClassName((int) $this->school_id, $className);
        }

        $group = $this->subjectGroups[$className];

        return $this->subjectAssignments()->contains(
            fn (array $assignment) => $assignment['class_name'] === $className
                && DefaultClassSubjects::sameSubject($assignment['subject'], $subject, $group)
        );
    }

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
