<?php

namespace App\Models;

use App\Enums\Gender;
use App\Models\Concerns\HasProtectedPhoto;
use App\Support\HasUuidRouteKey;
use Database\Factories\StudentFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    /** @use HasFactory<StudentFactory> */
    use Authenticatable, CanResetPassword, HasApiTokens, HasFactory, HasUuidRouteKey, Notifiable;

    use HasProtectedPhoto;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'admission_number',
        'first_name',
        'last_name',
        'gender',
        'date_of_birth',
        'class_name',
        'guardian_name',
        'guardian_phone',
        'guardian_email',
        'address',
        'phone',
        'email',
        'photo_path',
        'blood_group',
        'house',
        'admission_date',
        'is_active',
        'notes',
        'password',
        'must_change_password',
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
            'date_of_birth' => 'date',
            'admission_date' => 'date',
            'is_active' => 'boolean',
            'password' => 'hashed',
            'must_change_password' => 'boolean',
            'last_login_at' => 'datetime',
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
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<TransportAssignment, $this>
     */
    public function transportAssignment(): HasOne
    {
        return $this->hasOne(TransportAssignment::class);
    }

    /**
     * @return HasOne<HostelAllocation, $this>
     */
    public function hostelAllocation(): HasOne
    {
        return $this->hasOne(HostelAllocation::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<AssignmentSubmission, $this>
     */
    public function assignmentSubmissions(): HasMany
    {
        return $this->hasMany(AssignmentSubmission::class);
    }

    /**
     * @return HasMany<ExaminationScore, $this>
     */
    public function examinationScores(): HasMany
    {
        return $this->hasMany(ExaminationScore::class);
    }

    /**
     * @return HasMany<BookLoan, $this>
     */
    public function bookLoans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    /**
     * @return HasMany<CbtAttempt, $this>
     */
    public function cbtAttempts(): HasMany
    {
        return $this->hasMany(CbtAttempt::class);
    }

    /**
     * @return BelongsToMany<CoCurricularActivity, $this>
     */
    public function coCurricularActivities(): BelongsToMany
    {
        return $this->belongsToMany(CoCurricularActivity::class, 'co_curricular_activity_student')->withPivot('joined_at');
    }

    /**
     * @return BelongsToMany<Guardian, $this>
     */
    public function guardians(): BelongsToMany
    {
        return $this->belongsToMany(Guardian::class, 'guardian_student')->withPivot('relationship');
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
