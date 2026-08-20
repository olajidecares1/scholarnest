<?php

namespace App\Models;

use App\Enums\SubjectCategory;
use Database\Factories\SubjectFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Subject extends Model
{
    /** @use HasFactory<SubjectFactory> */
    use HasFactory;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'name',
        'category',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'category' => SubjectCategory::class,
        ];
    }

    /**
     * @return HasMany<SubjectOffering, $this>
     */
    public function offerings(): HasMany
    {
        return $this->hasMany(SubjectOffering::class);
    }
}
