<?php

namespace App\Models;

use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Support\HasUuidRouteKey;
use App\Support\TenantUrl;
use App\Support\WebsiteBlockDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
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
        'favicon_path',
        'timezone',
        'current_session',
        'billing_contact_name',
        'billing_email',
        'billing_phone',
        'billing_address',
        'principal_name',
        'principal_signature_path',
        'is_active',
        'deactivated_at',
        'automatic_grading',
        'school_code',
        'auto_generate_admission_numbers',
        'next_admission_sequence',
        'auto_generate_staff_ids',
        'next_staff_sequence',
    ];

    /**
     * Mirrors the schools table's column defaults: without this, a freshly
     * created-but-not-yet-refreshed School instance has is_active as null in
     * PHP (Eloquent doesn't re-fetch DB-applied defaults after an insert),
     * even though the database row itself defaults to true - which would
     * make hasActiveSubscription() silently false for a school checked in
     * the same request it was created in.
     *
     * @var array<string, mixed>
     */
    protected $attributes = [
        'is_active' => true,
        'automatic_grading' => true,
        'auto_generate_admission_numbers' => false,
        'next_admission_sequence' => 1,
        'auto_generate_staff_ids' => false,
        'next_staff_sequence' => 1,
    ];

    protected static function booted(): void
    {
        static::creating(function (self $school) {
            if (! $school->slug) {
                $base = Str::slug($school->name);
                $slug = $base;
                $suffix = 1;

                while (static::where('slug', $slug)->exists()) {
                    $slug = "{$base}-".++$suffix;
                }

                $school->slug = $slug;
            }

            // Short, memorable, and unique - unlike the slug (system-
            // generated, only ever shown in a public marketing URL, and a
            // Basic-plan school never even gets one of those), this is the
            // thing a school admin actually types in to disambiguate their
            // school on the shared /portal login when no custom domain
            // tells the backend which school they mean.
            if (! $school->school_code) {
                $base = Str::upper(Str::random(6));
                $code = $base;
                $suffix = 1;

                while (static::where('school_code', $code)->exists()) {
                    $code = "{$base}-".++$suffix;
                }

                $school->school_code = $code;
            }

            // Long, unguessable, and stable for the lifetime of the school -
            // not regenerated on access - so each of the four portal login
            // pages (school-admin/staff/student/guardian) can't be reached
            // just by knowing the school's public slug/subdomain.
            $school->portal_admin_token ??= Str::random(40);
            $school->portal_staff_token ??= Str::random(40);
            $school->portal_student_token ??= Str::random(40);
            $school->portal_guardian_token ??= Str::random(40);
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
            'automatic_grading' => 'boolean',
            'auto_generate_admission_numbers' => 'boolean',
            'auto_generate_staff_ids' => 'boolean',
        ];
    }

    public function logoUrl(): ?string
    {
        return $this->logo_path ? Storage::disk('public')->url($this->logo_path) : null;
    }

    public function faviconUrl(): ?string
    {
        return $this->favicon_path ? Storage::disk('public')->url($this->favicon_path) : null;
    }

    public function principalSignatureUrl(): ?string
    {
        return $this->principal_signature_path ? Storage::disk('public')->url($this->principal_signature_path) : null;
    }

    /**
     * Absolute local filesystem path to the logo, for use only in dompdf
     * views - dompdf's `enable_remote` option is off, so it can never fetch
     * logoUrl()'s http(s) URL, but it can read local files within its
     * configured chroot directly.
     */
    public function logoAbsolutePath(): ?string
    {
        return $this->logo_path && Storage::disk('public')->exists($this->logo_path)
            ? Storage::disk('public')->path($this->logo_path)
            : null;
    }

    public function principalSignatureAbsolutePath(): ?string
    {
        return $this->principal_signature_path && Storage::disk('public')->exists($this->principal_signature_path)
            ? Storage::disk('public')->path($this->principal_signature_path)
            : null;
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
     * @return HasMany<ResultCheckingPin, $this>
     */
    public function resultCheckingPins(): HasMany
    {
        return $this->hasMany(ResultCheckingPin::class);
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
     * Whether this school is allowed to use the system at all: not suspended,
     * and its current subscription has actually been approved by a Super
     * Admin (not merely submitted). This is the strict check every access
     * gate in the app must go through - activeSubscription() alone is not
     * enough, since it also matches PendingVerification/PendingPayment
     * subscriptions that no one has reviewed yet.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->is_active && $this->activeSubscription?->status === SubscriptionStatus::Active;
    }

    /**
     * Whether this school's current subscription is active and on one of the
     * given plans - the shared check behind every plan-gated feature
     * (portals, ID cards, custom domain).
     */
    public function hasPlanAccess(PlanKey ...$allowed): bool
    {
        return $this->hasActiveSubscription() && in_array($this->activeSubscription->plan->key, $allowed, true);
    }

    /**
     * The number of students this school is entitled to admit this term, or
     * null for plans that aren't sold per-student (Standard/Exclusive are
     * flat-fee, so they have no such cap). Reflects any approved top-ups,
     * since those are applied by increasing the active subscription's
     * students_count in place rather than tracked separately.
     */
    public function studentSlotLimit(): ?int
    {
        if (! $this->hasPlanAccess(PlanKey::Basic)) {
            return null;
        }

        return $this->activeSubscription->students_count;
    }

    /**
     * The maximum number of active teacher accounts this school may create,
     * or null if its plan has no such cap.
     */
    public function teacherAccountLimit(): ?int
    {
        if (! $this->hasActiveSubscription()) {
            return null;
        }

        return $this->activeSubscription->plan->max_teachers;
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
     * @return HasMany<Guardian, $this>
     */
    public function guardians(): HasMany
    {
        return $this->hasMany(Guardian::class);
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
     * @return HasMany<GradeBand, $this>
     */
    public function gradeBands(): HasMany
    {
        return $this->hasMany(GradeBand::class)->orderBy('position');
    }

    /**
     * @return HasMany<SubjectOffering, $this>
     */
    public function subjectOfferings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }

    /**
     * The subjects this school has configured as offered for a given class,
     * or an empty collection if it hasn't configured any yet - callers
     * should treat an empty result as "not configured" and fall back to
     * free-text subject entry, not "this class offers nothing".
     *
     * @return Collection<int, string>
     */
    public function offeredSubjectsFor(string $className): Collection
    {
        return $this->subjectOfferings()
            ->where('class_name', $className)
            ->with('subject')
            ->get()
            ->pluck('subject.name')
            ->sort()
            ->values();
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

    /**
     * @return HasMany<NavLink, $this>
     */
    public function navLinks(): HasMany
    {
        return $this->hasMany(NavLink::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<HeroSlide, $this>
     */
    public function heroSlides(): HasMany
    {
        return $this->hasMany(HeroSlide::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<WebsiteBlock, $this>
     */
    public function websiteBlocks(): HasMany
    {
        return $this->hasMany(WebsiteBlock::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<TimetableEntry, $this>
     */
    public function timetableEntries(): HasMany
    {
        return $this->hasMany(TimetableEntry::class);
    }

    /**
     * @return HasMany<CoCurricularActivity, $this>
     */
    public function coCurricularActivities(): HasMany
    {
        return $this->hasMany(CoCurricularActivity::class);
    }

    /**
     * @return HasMany<SchoolNotice, $this>
     */
    public function notices(): HasMany
    {
        return $this->hasMany(SchoolNotice::class);
    }

    /**
     * @return HasMany<CbtExamBodyClassGrant, $this>
     */
    public function cbtExamBodyClassGrants(): HasMany
    {
        return $this->hasMany(CbtExamBodyClassGrant::class);
    }

    /**
     * Returns this school's saved visual-builder blocks for the given public
     * page, or a generated default set (seeded from its current `SchoolWebsite`
     * fields) when nothing has been saved yet — mirroring the null-fallback
     * pattern used elsewhere, so an unconfigured school still renders correctly.
     *
     * @return Collection<int, array<string, mixed>>
     */
    public function websiteBlocksFor(string $page): Collection
    {
        $blocks = $this->websiteBlocks()->forPage($page)->get();

        if ($blocks->isNotEmpty()) {
            return $blocks->map(fn (WebsiteBlock $block) => [
                'uuid' => $block->uuid,
                'section' => $block->section,
                'type' => $block->type,
                'content' => $block->content,
                'secondary_content' => $block->secondary_content,
                'url' => $block->url,
                'x' => $block->x,
                'y' => $block->y,
                'w' => $block->w,
                'h' => $block->h,
                'style' => $block->style,
                'sort_order' => $block->sort_order,
            ])->values();
        }

        $website = $this->website;

        if (! $website) {
            return collect();
        }

        $defaults = match ($page) {
            'home' => WebsiteBlockDefaults::forHome($website),
            'about' => WebsiteBlockDefaults::forAbout($website),
            'admissions' => WebsiteBlockDefaults::forAdmissions($website),
            'contact' => WebsiteBlockDefaults::forContact($website),
            'footer' => WebsiteBlockDefaults::forFooter($website),
            default => [],
        };

        return collect($defaults)->map(fn (array $spec) => [
            'uuid' => 'default-'.$spec['key'],
            'section' => $spec['section'],
            'type' => $spec['type'],
            'content' => $spec['content'],
            'secondary_content' => $spec['secondary_content'],
            'url' => $spec['url'],
            'x' => $spec['x'],
            'y' => $spec['y'],
            'w' => $spec['w'],
            'h' => $spec['h'],
            'style' => $spec['style'],
            'sort_order' => $spec['sort_order'],
        ])->values();
    }

    /**
     * @return HasMany<TransportVehicle, $this>
     */
    public function transportVehicles(): HasMany
    {
        return $this->hasMany(TransportVehicle::class);
    }

    /**
     * @return HasMany<TransportRoute, $this>
     */
    public function transportRoutes(): HasMany
    {
        return $this->hasMany(TransportRoute::class);
    }

    /**
     * @return HasMany<Hostel, $this>
     */
    public function hostels(): HasMany
    {
        return $this->hasMany(Hostel::class);
    }

    /**
     * @return HasMany<NewsPost, $this>
     */
    public function newsPosts(): HasMany
    {
        return $this->hasMany(NewsPost::class);
    }

    /**
     * @return HasMany<JobPosting, $this>
     */
    public function jobPostings(): HasMany
    {
        return $this->hasMany(JobPosting::class);
    }

    /**
     * @return HasMany<Testimonial, $this>
     */
    public function testimonials(): HasMany
    {
        return $this->hasMany(Testimonial::class);
    }

    /**
     * @return HasMany<SchoolFacility, $this>
     */
    public function facilities(): HasMany
    {
        return $this->hasMany(SchoolFacility::class)->orderBy('sort_order');
    }

    /**
     * @return HasMany<IdCardTemplate, $this>
     */
    public function idCardTemplates(): HasMany
    {
        return $this->hasMany(IdCardTemplate::class);
    }

    /**
     * @return HasMany<IssuedIdCard, $this>
     */
    public function issuedIdCards(): HasMany
    {
        return $this->hasMany(IssuedIdCard::class);
    }

    /**
     * @return HasMany<CbtTest, $this>
     */
    public function cbtTests(): HasMany
    {
        return $this->hasMany(CbtTest::class);
    }

    /**
     * @return HasMany<CustomDomain, $this>
     */
    public function customDomains(): HasMany
    {
        return $this->hasMany(CustomDomain::class);
    }

    /**
     * @return HasOne<CustomDomain, $this>
     */
    public function primaryCustomDomain(): HasOne
    {
        return $this->hasOne(CustomDomain::class)->where('is_primary', true);
    }

    /**
     * The host this school's public website is actually reachable at, if
     * anything other than the default ednumest.com/schools/{slug} path
     * applies: an Exclusive school's verified custom domain, or a Standard
     * school's free ednumest.com subdomain (once TENANT_BASE_DOMAIN is
     * configured). Null means "use the default path" - the caller decides
     * what that means for its context (render vs. redirect).
     */
    public function resolvedPublicHost(): ?string
    {
        if ($this->hasPlanAccess(PlanKey::Exclusive)) {
            $primary = $this->primaryCustomDomain;

            if ($primary && $primary->isVerifiedAndActive()) {
                return $primary->domain;
            }
        }

        $baseDomain = config('custom_domain.tenant_base_domain');

        if ($baseDomain && $this->hasPlanAccess(PlanKey::Standard)) {
            return "{$this->slug}.{$baseDomain}";
        }

        return null;
    }

    /**
     * Builds the public URL for a "public.*" named route, using this
     * school's resolved custom domain or subdomain when one applies, and
     * falling back to the default {slug}-path route otherwise. Reuses the
     * same "generate against a throwaway host, then keep only the path/
     * query" trick as RedirectToCustomDomain, since route() otherwise has
     * no way to target an arbitrary host for a domain-bound route.
     *
     * @param  array<string, mixed>  $params
     */
    /**
     * The correct, school-scoped login page for a given auth guard - the
     * destination every logout (manual or idle-timeout) must return to
     * instead of the shared, school-agnostic /login page.
     */
    public function portalLoginUrl(string $guard): string
    {
        $route = match ($guard) {
            'web' => 'portal.admin.login',
            'student' => 'student.login',
            'staff' => 'staff.login',
            'guardian' => 'guardian.login',
            default => throw new \InvalidArgumentException("No portal login route for guard [{$guard}]."),
        };

        $token = match ($guard) {
            'web' => $this->portal_admin_token,
            'student' => $this->portal_student_token,
            'staff' => $this->portal_staff_token,
            'guardian' => $this->portal_guardian_token,
        };

        return $this->publicUrl($route, ['token' => $token]);
    }

    public function publicUrl(string $routeName, array $params = []): string
    {
        $host = $this->resolvedPublicHost();

        if (! $host) {
            return route($routeName, [...$params, 'school' => $this]);
        }

        $tenantRouteName = 'tenant.'.Str::after($routeName, 'public.');
        $generated = route($tenantRouteName, [...$params, 'tenantDomain' => 'edunest-placeholder-host.invalid']);
        $path = parse_url($generated, PHP_URL_PATH) ?? '/';
        $query = parse_url($generated, PHP_URL_QUERY);

        return TenantUrl::build($host, $path, $query ?: null);
    }
}
