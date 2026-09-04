<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\HasProtectedPhoto;
use App\Models\Concerns\HasSignature;
use App\Notifications\ResetPasswordNotification;
use App\Support\HasUuidRouteKey;
use Database\Factories\UserFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Foundation\Auth\User as Authenticatable;
use Illuminate\Notifications\Notifiable;
use Illuminate\Support\Str;

class User extends Authenticatable
{
    /** @use HasFactory<UserFactory> */
    use HasFactory, HasSignature, HasUuidRouteKey, Notifiable;

    use HasProtectedPhoto;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'email',
        'username',
        'password',
        'role',
        'photo_path',
        'school_id',
        'admin_role_id',
        'is_active',
    ];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<AdminRole, $this>
     */
    public function adminRole(): BelongsTo
    {
        return $this->belongsTo(AdminRole::class);
    }

    public function hasPermission(string $permission): bool
    {
        if ($this->role !== UserRole::SuperAdmin) {
            return false;
        }

        if ($this->admin_role_id === null) {
            return true;
        }

        return in_array($permission, $this->adminRole?->permissions ?? [], true);
    }

    public static function generateUniqueUsernameFromEmail(string $email): string
    {
        $base = Str::slug(Str::before($email, '@'), '_') ?: 'user';
        $username = $base;
        $suffix = 1;

        while (self::where('username', $username)->exists()) {
            $username = $base.$suffix;
            $suffix++;
        }

        return $username;
    }

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
            'email_verified_at' => 'datetime',
            'password' => 'hashed',
            'role' => UserRole::class,
            'is_active' => 'boolean',
        ];
    }

    /**
     * ScholarNest's own reset email, not Laravel's default.
     *
     * Overridden here rather than configured with ResetPassword::toMailUsing()
     * in a service provider, because this is the only model that has a reset
     * flow at all - Staff, Student and Guardian deliberately do not, see
     * docs/PASSWORD-RESET-POLICY.md - and a global callback would suggest
     * otherwise to anyone reading it.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        $this->notify(new ResetPasswordNotification($token, $this->email));
    }
}
