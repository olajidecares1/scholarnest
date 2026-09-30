<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * An admission number or Staff ID sequence freed by a deletion, waiting to
 * be issued to the next registration. See IdentifierGenerator.
 */
class ReleasedIdentifier extends Model
{
    protected $fillable = ['school_id', 'type', 'sequence'];

    protected function casts(): array
    {
        return ['sequence' => 'integer'];
    }
}
