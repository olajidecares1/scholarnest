<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\BookFactory;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;

class Book extends Model
{
    /** @use HasFactory<BookFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The attributes that are mass assignable.
     *
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'title',
        'author',
        'isbn',
        'category',
        'copies_total',
        'copies_available',
    ];

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return HasMany<BookLoan, $this>
     */
    public function loans(): HasMany
    {
        return $this->hasMany(BookLoan::class);
    }

    public function copiesOnLoan(): int
    {
        return $this->copies_total - $this->copies_available;
    }
}
