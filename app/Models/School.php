<?php

namespace App\Models;

use App\Enums\SubscriptionStatus;
use App\Support\HasUuidRouteKey;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Facades\Storage;
use Illuminate\Support\Str;

class School extends Model
{
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'logo_path',
        'timezone',
        'current_session',
        'billing_contact_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'is_active',
        'deactivated_at',
    ];

    protected static function booted(): void
    {
        static::creating(function (self $school) {
            if ($school->slug) {
                return;
            }

            $base = Str::slug($school->name);
            $slug = $base;
            $suffix = 1;

            while (static::where('slug', $slug)->exists()) {
                $slug = "{$base}-".++$suffix;
            }

            $school->slug = $slug;
        });
    }

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'is_active' => 'boolean',
            'deactivated_at' => 'datetime',
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }

    /**
     * @return HasMany<Subscription, $this>
     */
    public function subscriptions(): HasMany
    {
        return $this->hasMany(Subscription::class);
    }

    /**
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)
            ->whereIn('status', [SubscriptionStatus::Active, SubscriptionStatus::PendingVerification, SubscriptionStatus::PendingPayment])
            ->latestOfMany();
    }

    /**
     * @return HasMany<SupportTicket, $this>
     */
    public function supportTickets(): HasMany
    {
        return $this->hasMany(SupportTicket::class);
    }

    /**
     * @return HasMany<Student, $this>
     */
    public function students(): HasMany
    {
        return $this->hasMany(Student::class);
    }

    /**
     * @return HasMany<AcademicLevel, $this>
     */
    public function academicLevels(): HasMany
    {
        return $this->hasMany(AcademicLevel::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<SchoolClass, $this>
     */
    public function schoolClasses(): HasMany
    {
        return $this->hasMany(SchoolClass::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<Staff, $this>
     */
    public function staff(): HasMany
    {
        return $this->hasMany(Staff::class);
    }

    /**
     * @return HasMany<AttendanceRecord, $this>
     */
    public function attendanceRecords(): HasMany
    {
        return $this->hasMany(AttendanceRecord::class);
    }

    /**
     * @return HasMany<Examination, $this>
     */
    public function examinations(): HasMany
    {
        return $this->hasMany(Examination::class);
    }

    /**
     * @return HasMany<Assignment, $this>
     */
    public function assignments(): HasMany
    {
        return $this->hasMany(Assignment::class);
    }

    /**
     * @return HasMany<SchoolEvent, $this>
     */
    public function events(): HasMany
    {
        return $this->hasMany(SchoolEvent::class);
    }

    /**
     * @return HasMany<Book, $this>
     */
    public function books(): HasMany
    {
        return $this->hasMany(Book::class);
    }

    /**
     * @return HasMany<FeeStructure, $this>
     */
    public function feeStructures(): HasMany
    {
        return $this->hasMany(FeeStructure::class);
    }

    /**
     * @return HasMany<Invoice, $this>
     */
    public function invoices(): HasMany
    {
        return $this->hasMany(Invoice::class);
    }

    /**
     * @return HasOne<SchoolWebsite, $this>
     */
    public function website(): HasOne
    {
        return $this->hasOne(SchoolWebsite::class);
    }

    /**
     * @return HasMany<SchoolGalleryImage, $this>
     */
    public function galleryImages(): HasMany
    {
        return $this->hasMany(SchoolGalleryImage::class)->orderBy('sort_order');
    }
}
