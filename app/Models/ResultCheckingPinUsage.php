<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ResultCheckingPinUsageFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

class ResultCheckingPinUsage extends Model
{
    /** @use HasFactory<ResultCheckingPinUsageFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'result_checking_pin_id',
        'student_id',
        'examination_id',
        'ip_address',
        'used_at',
    ];

    /**
     * Get the attributes that should be cast.
     *
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'used_at' => 'datetime',
        ];
    }

    /**
     * @return BelongsTo<ResultCheckingPin, $this>
     */
    public function pin(): BelongsTo
    {
        return $this->belongsTo(ResultCheckingPin::class, 'result_checking_pin_id');
    }

    /**
     * @return BelongsTo<Student, $this>
     */
    public function student(): BelongsTo
    {
        return $this->belongsTo(Student::class);
    }

    /**
     * @return BelongsTo<Examination, $this>
     */
    public function examination(): BelongsTo
    {
        return $this->belongsTo(Examination::class);
    }
}
