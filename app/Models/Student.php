<?php

namespace App\Models;

use App\Enums\Gender;
use App\Enums\RegistrationSource;
use App\Models\Concerns\HasProtectedPhoto;
use App\Services\IdentifierGenerator;
use App\Support\HasUuidRouteKey;
use Database\Factories\StudentFactory;
use Illuminate\Auth\Authenticatable;
use Illuminate\Auth\Passwords\CanResetPassword;
use Illuminate\Contracts\Auth\Authenticatable as AuthenticatableContract;
use Illuminate\Contracts\Auth\CanResetPassword as CanResetPasswordContract;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\BelongsToMany;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Collection;
use Laravel\Sanctum\HasApiTokens;

class Student extends Model implements AuthenticatableContract, CanResetPasswordContract
{
    /** @use HasFactory<StudentFactory> */
    use Authenticatable, CanResetPassword, HasApiTokens, HasFactory, HasUuidRouteKey, Notifiable;

    use HasProtectedPhoto;

    /**
     * Set only while correctingAdmissionNumber() runs.
     */
    private static bool $admissionNumberCorrection = false;

    /**
     * A student's admission number is their permanent identity at the
     * school: their ID card, results, fees and sign-in all go by it. Once
     * given, nothing changes it by the way: not a change of class, a
     * promotion, an edit, an import or a teacher's correction of their
     * details. Any change to it made through the model is put back.
     *
     * The two deliberate exceptions: a School Admin correcting a number they
     * typed by hand (correctingAdmissionNumber(), from the student's edit
     * form, recorded in identifier_changes and the activity log), and the
     * School Code renumbering (IdentifierRegenerator), which writes to the
     * table directly and keeps the old numbers traceable.
     *
     * A deleted record's admission number goes back in the pool and is given
     * to the next one registered; any ID card issued to them is revoked.
     */
    protected static function booted(): void
    {
        static::updating(function (self $record) {
            if ($record->isDirty('admission_number') && ! self::$admissionNumberCorrection) {
                $record->admission_number = $record->getOriginal('admission_number');
            }
        });

        static::deleted(function (self $record) {
            app(IdentifierGenerator::class)->release($record);
            IssuedIdCard::revokeFor($record->school_id, $record->uuid);
        });
    }

    /**
     * Run $callback with the admission number open to a deliberate change.
     *
     * @template T
     *
     * @param  callable(): T  $callback
     * @return T
     */
    public static function correctingAdmissionNumber(callable $callback): mixed
    {
        self::$admissionNumberCorrection = true;

        try {
            return $callback();
        } finally {
            self::$admissionNumberCorrection = false;
        }
    }

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
        'registration_source',
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
            'registration_source' => RegistrationSource::class,
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
     * Term totals entered by hand. See App\Models\AttendanceSummary.
     *
     * @return HasMany<AttendanceSummary, $this>
     */
    public function attendanceSummaries(): HasMany
    {
        return $this->hasMany(AttendanceSummary::class);
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

    /**
     * The one order every list of students/pupils uses: alphabetical by
     * first name, then surname, the way names are shown (fullName()), so a
     * list reads A to Z down the page. The id breaks ties between two
     * children with the same name so the order never shifts.
     *
     * @param  Builder<Student>  $query
     * @return Builder<Student>
     */
    public function scopeAlphabetical(Builder $query): Builder
    {
        $table = $query->getModel()->getTable();

        return $query
            ->orderBy("{$table}.first_name")
            ->orderBy("{$table}.last_name")
            ->orderBy("{$table}.id");
    }

    /**
     * The same alphabetical order for a list already loaded, e.g. hostel
     * allocations or rows keyed by student. $student picks the Student out
     * of each item; by default the items are Students themselves.
     *
     * @template TItem
     *
     * @param  iterable<TItem>  $items
     * @param  (callable(TItem): ?Student)|null  $student
     * @return Collection<int, TItem>
     */
    public static function sortAlphabetically(iterable $items, ?callable $student = null): Collection
    {
        $student ??= fn ($item) => $item;
        $key = function ($item) use ($student): array {
            $s = $student($item);

            return [
                mb_strtolower(trim((string) $s?->first_name)),
                mb_strtolower(trim((string) $s?->last_name)),
                (int) $s?->id,
            ];
        };

        return collect($items)
            ->sort(fn ($a, $b) => $key($a) <=> $key($b))
            ->values();
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
