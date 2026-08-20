<?php

namespace App\Models;

use Database\Factories\AuditLogFactory;
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
        'user_id',
        'user_name',
        'action',
        'description',
        'subject_type',
        'subject_id',
        'ip_address',
    ];

    /**
     * @return BelongsTo<User, $this>
     */
    public function user(): BelongsTo
    {
        return $this->belongsTo(User::class);
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
            'user_id' => $user?->id,
            'user_name' => $actorName ?? $user?->name ?? 'System',
            'action' => $action,
            'description' => $description,
            'subject_type' => $subject?->getMorphClass(),
            'subject_id' => $subject?->getKey(),
            'ip_address' => Request::ip(),
        ]);
    }
}
