<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\StaffRole;
use App\Enums\TeacherAssignmentType;
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
use Illuminate\Support\Facades\Storage;

class Staff extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    /** @use HasFactory<StaffFactory> */
    use Authenticatable, CanResetPassword, HasFactory, HasUuidRouteKey, Notifiable;

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
     * Every class this teacher is the Class Teacher of - a teacher may hold
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

    public function fullName(): string
    {
        return "{$this->first_name} {$this->last_name}";
    }

    public function photoUrl(): ?string
    {
        return $this->photo_path ? Storage::disk('public')->url($this->photo_path) : null;
    }

    /**
     * Absolute local filesystem path to the photo, for use only in dompdf
     * views - dompdf's `enable_remote` option is off, so it can never fetch
     * photoUrl()'s http(s) URL, but it can read local files within its
     * configured chroot directly.
     */
    public function photoAbsolutePath(): ?string
    {
        return $this->photo_path && Storage::disk('public')->exists($this->photo_path)
            ? Storage::disk('public')->path($this->photo_path)
            : null;
    }

    public function age(): ?int
    {
        return $this->date_of_birth?->age;
    }
}
