<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\MorphTo;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Request;

class AuditLog extends Model
{
    /** @use HasFactory<AuditLogFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'user_id',
        'user_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
    ];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
    }

    /**
     * Entries belonging to one school, newest first.
     *
     * A School Admin must never be shown another school's history, and the
     * platform-level entries with no school (the AkademicNest Team editing plan
     * pricing, say) are nobody's business but the Team's - so a null school_id
     * is excluded here rather than treated as "matches everyone".
     *
     * @param  Builder<self>  $query
     * @return Builder<self>
     */
    public function scopeForSchool($query, School|int $school)
    {
        return $query->where('school_id', $school instanceof School ? $school->getKey() : $school);
    }

    /**
     * @return MorphTo<Model, $this>
     */
    public function subject(): MorphTo
    {
        return $this->morphTo();
    }

    /**
     * $actorName is for callers acting on behalf of a non-"web"-guard actor
     * (student/staff/guardian) - user_id is a foreign key into the "users"
     * table (School Admin/Super Admin accounts) only, so a Auth::user()
     * lookup is meaningless (and, worse, a same-numbered id from another
     * guard's table would misattribute the log) outside that guard. Passing
     * $actorName bypasses the Auth::user() derivation entirely and records
     * a human-readable actor with no user_id, rather than guessing.
     */
    public static function record(string $action, string $description, ?Model $subject = null, ?string $actorName = null): self
    {
        $user = $actorName === null ? Auth::user() : null;

        return self::create([
            'school_id' => self::resolveSchoolId($subject),
            'user_id' => $user?->id,
            'user_name' => $actorName ?? $user?->name ?? 'System',
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => Request::ip(),
        ]);
    }

    /**
     * Which school an entry belongs to.
     *
     * Resolved here rather than at the ninety-odd call sites, because a rule
     * applied by hand in ninety places is a rule with exceptions. The order
     * matters:
     *
     * 1. The subject. If the entry is about a school's own record - a pupil, a
     *    result token, an invoice - then that record's school is the answer,
     *    and it stays right even when the AkademicNest Team is the one acting.
     * 2. The acting user, across every guard. Staff, students and guardians
     *    are all bound to a school; a School Admin's user row carries one too.
     * 3. Null, for entries that genuinely belong to no school: the AkademicNest
     *    Team editing CBT question banks, plan pricing or the marketing site.
     *
     * Null is a real answer, not a failure. Those entries stay in the Team's
     * platform-wide log and out of every school's.
     */
    private static function resolveSchoolId(?Model $subject): ?int
    {
        if ($subject instanceof School) {
            return $subject->getKey();
        }

        if ($subject !== null && isset($subject->school_id)) {
            return (int) $subject->school_id;
        }

        foreach (['web', 'staff', 'student', 'guardian'] as $guard) {
            $actor = Auth::guard($guard)->user();

            if ($actor !== null && isset($actor->school_id)) {
                return (int) $actor->school_id;
            }
        }

        return null;
    }
}
