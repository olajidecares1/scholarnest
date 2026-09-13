<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;

/**
 * One megabyte (or the remainder) of a StoredFile, base64-encoded.
 */
class StoredFileChunk extends Model
{
    public $timestamps = false;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'stored_file_id',
        'sequence',
        'data',
    ];
}
