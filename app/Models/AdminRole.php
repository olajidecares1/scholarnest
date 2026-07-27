<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\AdminRoleFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Str;

class AdminRole extends Model
{
    /** @use HasFactory<AdminRoleFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * @var array<string, string>
     */
    public const PERMISSIONS = [
        'manage_schools' => 'Manage Schools',
        'manage_subscriptions' => 'Manage Subscriptions',
        'manage_payments' => 'View Payments',
        'manage_users' => 'Manage Users',
        'manage_roles' => 'Manage Roles & Permissions',
        'manage_reports' => 'Manage Reports',
        'manage_analytics' => 'View Analytics',
        'manage_communications' => 'Manage Communications',
        'manage_support_tickets' => 'Manage Support Tickets',
        'manage_cms' => 'Manage CMS',
        'manage_themes' => 'Manage Themes',
        'manage_media' => 'Manage Media Library',
        'manage_settings' => 'Manage System Settings',
        'manage_audit_logs' => 'View Audit Logs',
    ];

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'slug',
        'permissions',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'permissions' => 'array',
        ];
    }

    protected static function booted(): void
    {
        static::creating(function (AdminRole $role): void {
            $role->slug ??= Str::slug($role->name);
        });
    }

    /**
     * @return HasMany<User, $this>
     */
    public function users(): HasMany
    {
        return $this->hasMany(User::class);
    }
}
