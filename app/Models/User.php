<?php

namespace App\Models;

// use Illuminate\Contracts\Auth\MustVerifyEmail;
use App\Enums\UserRole;
use App\Models\Concerns\HasProtectedPhoto;
use App\Models\Concerns\HasSignature;
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
     * Laravel's link-based reset is not used. "Forgot password?" sends a
     * 6-digit code instead, see App\Http\Controllers\Auth\PasswordResetController.
     * Refusing here means nothing can quietly fall back to Laravel's default
     * email with a link in it.
     */
    public function sendPasswordResetNotification(#[\SensitiveParameter] $token): void
    {
        throw new \LogicException('Password resets use a 6-digit code. See PasswordResetController.');
    }
}
