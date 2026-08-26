<?php

namespace App\Models;

use App\Enums\PlanFeature;
use App\Enums\PlanKey;
use App\Enums\SubscriptionStatus;
use App\Support\AcademicSession;
use App\Support\HasUuidRouteKey;
use App\Support\TenantUrl;
use App\Support\WebsiteBlockDefaults;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Database\Eloquent\Relations\HasOne;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\DB;
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
        'result_link_slug',
        'result_link_enabled',
        'auto_generate_admission_numbers',
        'next_admission_sequence',
        'auto_generate_staff_ids',
        'next_staff_sequence',
        'next_guardian_sequence',
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
        'next_guardian_sequence' => 1,
    ];

    /**
     * Would this slug be mistaken for the Basic-plan portal token?
     *
     * The portal lives at the root of the site - edunest.com/{32-char token} -
     * and so do school slugs. The token route is registered first and therefore
     * wins, which means a school whose slug happened to be 32 unbroken
     * alphanumeric characters would be permanently unreachable: every request
     * for it would hit the token check and 404.
     *
     * A real school name almost always contains a space, and so a hyphen, which
     * is enough to avoid this. "Almost always" is not good enough when the
     * failure mode is a customer whose site simply does not exist, so such
     * slugs are refused outright and given a numeric suffix instead.
     */
    private static function looksLikeAPortalToken(string $slug): bool
    {
        return (bool) preg_match('/^[a-z0-9]{32}$/', $slug);
    }

    protected static function booted(): void
    {
        static::creating(function (self $school) {
            if (! $school->slug) {
                // The slug is also the school's address at the root of the
                // platform - edunest.com/greenfield-college - so it competes
                // for names with the application's own top-level paths. A
                // school that managed to claim "login" or "dashboard" would
                // be a genuine problem, so those names are skipped here as
                // well as excluded by the route pattern. See
                // config/basic_portal.php.
                $reserved = array_map('strtolower', (array) config('basic_portal.reserved_slugs', []));

                // Falls back to "school" for a name made entirely of
                // characters Str::slug() strips, which would otherwise
                // produce an empty slug and an unroutable school.
                $base = Str::slug($school->name) ?: 'school';
                $slug = $base;
                $suffix = 1;

                while (
                    in_array($slug, $reserved, true)
                    || self::looksLikeAPortalToken($slug)
                    || static::where('slug', $slug)->exists()
                ) {
                    $slug = "{$base}-".++$suffix;
                }

                $school->slug = $slug;
            }

            // Short, memorable, and unique - unlike the slug, which is
            // generated from the name and so is easy to get slightly wrong.
            // This is the thing a school admin actually types in to
            // disambiguate their school on the shared /portal login when no
            // custom domain tells the backend which school they mean, and
            // what the Basic-plan school finder shows to tell two similarly
            // named schools apart.
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

            // The school's own result-checking address, seeded from the slug
            // so it reads as the school does: /greenfield-college/result.
            // Kept as its own value because a school must be able to retire a
            // link that has spread too far without renaming itself.
            $school->result_link_slug ??= self::availableResultLinkSlug($school->slug);
        });

        /*
         * Sign-in residue, which no foreign key can reach.
         *
         * The school's accounts go with it - users.school_id cascades - and
         * every table hung off a school or a user cascades in turn. Two do
         * not, because they have no foreign key at all:
         *
         *   sessions, keyed by user_id with no constraint. A row left here
         *   keeps a deleted account's browser signed in.
         *
         *   password_reset_tokens, keyed by email and nothing else. A row
         *   left here is a live way back into an account that is gone, and it
         *   would be handed to whoever registers that address next.
         *
         * Read BEFORE the delete, because a moment later there are no users
         * left to read the addresses from.
         */
        static::deleting(function (self $school) {
            $accounts = DB::table('users')
                ->where('school_id', $school->id)
                ->get(['id', 'email']);

            if ($accounts->isEmpty()) {
                return;
            }

            DB::table('sessions')->whereIn('user_id', $accounts->pluck('id'))->delete();
            DB::table('password_reset_tokens')->whereIn('email', $accounts->pluck('email'))->delete();
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
            'result_link_enabled' => 'boolean',
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
     * The subscription that grants this school access: an approved one.
     *
     * Approved only, deliberately. It used to match PendingVerification and
     * PendingPayment too and take whichever row was newest, which quietly
     * broke renewals: a school with a perfectly good approved subscription
     * submitted its next term, the new pending row was newer, and the school
     * lost access to everything it had paid for until somebody approved the
     * renewal. The same newest-row rule made an unreviewed application look
     * like the school's current plan.
     *
     * Nothing here can grant access on its own - only a Super Admin writing
     * Active can put a row in scope of this relation.
     *
     * @return HasOne<Subscription, $this>
     */
    public function activeSubscription(): HasOne
    {
        // The status constraint goes INSIDE ofMany's closure, not chained after
        // it. Chained, the aggregate picks the newest subscription of any
        // status first and the status filter is applied to that single row
        // afterwards - so a pending renewal wins the aggregate and is then
        // discarded, leaving the school with no active subscription at all.
        return $this->hasOne(Subscription::class)->ofMany(
            ['id' => 'max'],
            fn ($query) => $query->where('status', SubscriptionStatus::Active),
        );
    }

    /**
     * The school's most recent application, whatever became of it.
     *
     * For SHOWING state, never for granting it: a school waiting on approval
     * has nothing in activeSubscription() and still needs to be told what it
     * submitted and that it is pending.
     *
     * @return HasOne<Subscription, $this>
     */
    public function latestSubscription(): HasOne
    {
        return $this->hasOne(Subscription::class)->latestOfMany();
    }

    /**
     * What to display as this school's subscription.
     *
     * The approved one when there is one, otherwise the latest application.
     * Access decisions must not use this - they use hasActiveSubscription() -
     * but every screen that prints a plan name or a status badge should, so
     * that a pending school reads as pending rather than as having no plan.
     */
    public function subscriptionForDisplay(): ?Subscription
    {
        return $this->activeSubscription ?? $this->latestSubscription;
    }

    /**
     * Whether this school is allowed to use the system at all: not suspended,
     * and its subscription has actually been approved by a Super Admin (not
     * merely submitted). This is the strict check every access gate in the app
     * must go through.
     *
     * `is_active` alone is never enough. It defaults to true on creation and
     * means only "not suspended", so a school that has just registered and
     * paid nothing satisfies it.
     */
    public function hasActiveSubscription(): bool
    {
        return $this->is_active && $this->activeSubscription !== null;
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
     * The class names this school has configured, in the order it arranged
     * them - Creche, Nursery 1, ... , SSS 1 Science, SSS 1 Commercial.
     *
     * The single source for every place a class has to be CHOSEN rather than
     * typed. A typed class name is how a school ends up with its students
     * filed under "SSS 1 Science" and a token batch issued against
     * "SSS1 Science" - two classes, one of them empty, and no obvious reason
     * why the batch came out at zero.
     *
     * The name alone, because that is what students.class_name holds and what
     * every other class picker in the application matches on. The stream is a
     * separate attribute of the class, not part of its name.
     *
     * The academic year this school is actually in.
     *
     * The school's own setting wins over the calendar. AcademicSession::current()
     * guesses from the date - September onwards is the new year - and that guess
     * is wrong for any school whose year turned over earlier or later. A school
     * that has told us it is in 2026/2027 was being shown 2025/2026 on every
     * page that defaulted from the calendar, which made result pages look empty
     * when the records were simply filed under the year the school is in.
     */
    public function currentSession(): string
    {
        return filled($this->current_session)
            ? (string) $this->current_session
            : AcademicSession::current();
    }

    /**
     * Classes that hold students are included even if they were never entered
     * into the academic structure. A school whose records predate that
     * structure, or which imported its students, would otherwise find its own
     * classes missing from every dropdown - and a picker that cannot offer the
     * class you need is worse than the free-text field it replaced.
     *
     * @return list<string>
     */
    public function configuredClassNames(): array
    {
        $configured = $this->schoolClasses()
            ->orderBy('sort_order')
            ->orderBy('name')
            ->pluck('name');

        $inUse = $this->students()
            ->whereNotNull('class_name')
            ->distinct()
            ->orderBy('class_name')
            ->pluck('class_name');

        return $configured->merge($inUse)
            ->filter()
            ->unique()
            ->values()
            ->all();
    }

    /**
     * Whether this school's plan gives students and parents sign-in accounts
     * of their own.
     *
     * The staff portal is on every plan - teachers need it to do the school's
     * own work - but the student and parent portals are the outward-facing
     * tier Basic does not buy. A Basic parent still reaches results, through
     * an exam token, which needs no account at all.
     */
    public function hasPortalAccounts(): bool
    {
        return $this->hasPlanAccess(PlanKey::Standard, PlanKey::Exclusive);
    }

    /**
     * Does the result-checking link serve published cards, or live marks?
     *
     * Basic schools have no portals, so the checking link is the ONLY way a
     * result reaches a family - which is exactly why it must serve what the
     * school approved rather than whatever the scores table says this minute.
     * A parent who opens a card mid-entry has been shown an unfinished result
     * and told it was final.
     *
     * Standard and Exclusive keep their existing behaviour: their families
     * read results in a portal, and those rules are deliberately untouched.
     *
     * One method rather than a plan check at the call sites, because this is
     * the seam. If the repository should later back the checking link on every
     * plan, this returns true and nothing else moves.
     */
    public function resultsComeFromRepository(): bool
    {
        return $this->hasPlanAccess(PlanKey::Basic);
    }

    /**
     * Whether this school's plan includes a given feature.
     */
    public function canUseFeature(PlanFeature $feature): bool
    {
        return $this->hasPlanAccess(...$feature->requiredPlans());
    }

    /**
     * Whether this school may reach a route, by name.
     *
     * The interface asks this before it draws a link; the middleware asks the
     * same question when the link is followed. Both answers come from the same
     * place, which is the point: a nav item the backend would refuse is worse
     * than no nav item, and keeping a hand-written list of "premium routes"
     * beside the middleware is how the two drifted apart in the first place.
     */
    public function canAccessRoute(?string $routeName): bool
    {
        $feature = PlanFeature::forRoute($routeName);

        return $feature === null || $this->canUseFeature($feature);
    }

    // -------------------------------------------------------------------------
    // Result-checking link
    // -------------------------------------------------------------------------

    /**
     * @return HasMany<RetiredSchoolResultLink, $this>
     */
    public function retiredResultLinks(): HasMany
    {
        return $this->hasMany(RetiredSchoolResultLink::class);
    }

    /**
     * This school's result-checking address: edunest.com/greenfield-college/result
     *
     * The one thing a Basic school hands to parents. It carries no secret - a
     * token is still required to see anything - so it is deliberately readable
     * enough to print on a slip and type in by hand.
     */
    public function resultLinkUrl(): string
    {
        return route('school-result.show', ['school' => $this->result_link_slug]);
    }

    /**
     * Is this school's result link answering?
     *
     * Revoking is separate from regenerating: the address stays reserved to
     * this school, it just stops working until the school turns it back on.
     */
    public function resultLinkIsLive(): bool
    {
        return (bool) $this->result_link_enabled;
    }

    /**
     * Issue a fresh result-checking address and retire the current one.
     *
     * For a link that has spread further than the school intended. The old
     * value is recorded as retired rather than released, so it can never be
     * handed to another school and quietly send a parent holding the old link
     * somewhere they did not mean to go.
     */
    public function regenerateResultLink(): string
    {
        $previous = $this->result_link_slug;

        return DB::transaction(function () use ($previous): string {
            if ($previous !== null) {
                RetiredSchoolResultLink::firstOrCreate(
                    ['slug' => $previous],
                    ['school_id' => $this->id, 'retired_at' => now()],
                );
            }

            // Suffixed from the school's own slug rather than made random, so
            // the new address still reads as the school and a parent can be
            // told "it is the same link with -2 on the end".
            $this->forceFill([
                'result_link_slug' => self::availableResultLinkSlug($this->slug),
            ])->save();

            return $this->result_link_slug;
        });
    }

    /**
     * The first result-link address derived from $base that nobody holds and
     * nobody has ever held.
     *
     * Checks retired values as well as live ones. A slug that was released
     * back into the pool would eventually be reissued to a different school,
     * and every parent still holding the old link would land on it.
     */
    public static function availableResultLinkSlug(?string $base): string
    {
        $reserved = array_map('strtolower', (array) config('basic_portal.reserved_slugs', []));

        $base = Str::slug((string) $base) ?: 'school';
        $slug = $base;
        $suffix = 1;

        while (
            in_array($slug, $reserved, true)
            || self::looksLikeAPortalToken($slug)
            || static::where('result_link_slug', $slug)->exists()
            || RetiredSchoolResultLink::where('slug', $slug)->exists()
        ) {
            $slug = "{$base}-".++$suffix;
        }

        return $slug;
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
