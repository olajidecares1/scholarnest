<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\HostelRoomFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class HostelRoom extends Model
{
    /** @use HasFactory<HostelRoomFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'hostel_id',
        'room_number',
        'capacity',
    ];

    /**
     * @return BelongsTo<Hostel, $this>
     */
    public function hostel(): BelongsTo
    {
        return $this->belongsTo(Hostel::class);
    }

    /**
     * @return HasMany<HostelAllocation, $this>
     */
    public function allocations(): HasMany
    {
        return $this->hasMany(HostelAllocation::class);
    }

    public function occupancy(): int
    {
        return $this->allocations()->count();
    }
}
