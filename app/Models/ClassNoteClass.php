<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;

/**
 * One class a note was sent to.
 *
 * A row rather than an entry in a JSON column, so "every note for JSS 1A" is
 * an indexed lookup and the relationship between a note and its classes is
 * something the database enforces rather than something the application
 * remembers to encode the same way each time.
 */
class ClassNoteClass extends Model
{
    /**
     * @var list<string>
     */
    protected $fillable = [
        'class_note_id',
        'class_name',
    ];

    public $timestamps = false;

    /**
     * @return BelongsTo<ClassNote, $this>
     */
    public function classNote(): BelongsTo
    {
        return $this->belongsTo(ClassNote::class);
    }
}
