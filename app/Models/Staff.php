<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
use App\Models\Concerns\HasProtectedPhoto;
use App\Models\Concerns\HasSignature;
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
     * The table associated with the model.
     *
     * @var string
     */
    protected $table = 'staff';

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

        return $this->subjectAssignments()->contains(
            fn (array $assignment) => $assignment['class_name'] === $className
                && $assignment['subject'] === $subject
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
