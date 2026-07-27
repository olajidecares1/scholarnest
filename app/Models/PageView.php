<?php

namespace App\Models;

use Database\Factories\PageViewFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Support\Str;

class PageView extends Model
{
    /** @use HasFactory<PageViewFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'path',
        'route_name',
        'referrer_host',
        'traffic_source',
        'device_type',
        'ip_address',
        'viewed_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'viewed_at' => 'datetime',
        ];
    }

    /**
     * A human-readable label for this page, derived from the route name
     * (e.g. "subscriptions.choose-plan" -> "Subscriptions Choose Plan").
     * URLs themselves are opaque tokens, so this is how admins see what
     * a visit was actually for. Falls back to the raw path for rows
     * recorded before route names were tracked.
     */
    public function label(): string
    {
        if (! $this->route_name) {
            return $this->path;
        }

        return Str::of($this->route_name)
            ->replace(['.', '-', '_'], ' ')
            ->title()
            ->toString();
    }
}
