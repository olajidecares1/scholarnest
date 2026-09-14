<?php

namespace App\Models;

use App\Support\HasUuidRouteKey;
use Database\Factories\ClassNoteFactory;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Database\Eloquent\Factories\HasFactory;
use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\BelongsTo;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Collection;
use Illuminate\Support\Facades\Storage;

/**
 * A Word document a teacher sent to one or more classes.
 *
 * The file is stored ONCE, on the private disk, whichever classes receive it,
 * the classes are rows in class_note_classes, not copies of the document. It
 * is never given a URL: every read goes through a controller that has checked
 * the school first, and for a pupil the class as well.
 */
class ClassNote extends Model
{
    /** @use HasFactory<ClassNoteFactory> */
    use HasFactory, HasUuidRouteKey;

    /**
     * The disk these live on.
     *
     * Private. A class note is a school's own teaching material and may name
     * pupils; nothing about it belongs in a directory the web server will
     * hand to anybody who guesses the path.
     */
    public const DISK = 'local';

    public const DIRECTORY = 'class-notes';

    /**
     * @var list<string>
     */
    protected $fillable = [
        'school_id',
        'staff_id',
        'title',
        'subject',
        'description',
        'path',
        'original_name',
        'mime_type',
        'size_bytes',
        'body_text',
    ];

    /**
     * @return array<string, string>
     */
    protected function casts(): array
    {
        return [
            'size_bytes' => 'integer',
        ];
    }

    /**
     * @return BelongsTo<School, $this>
     */
    public function school(): BelongsTo
    {
        return $this->belongsTo(School::class);
    }

    /**
     * @return BelongsTo<Staff, $this>
     */
    public function staff(): BelongsTo
    {
        return $this->belongsTo(Staff::class);
    }

    /**
     * @return HasMany<ClassNoteClass, $this>
     */
    public function classes(): HasMany
    {
        return $this->hasMany(ClassNoteClass::class);
    }

    /**
     * The class names this note was sent to.
     *
     * @return Collection<int, string>
     */
    public function classNames(): Collection
    {
        return $this->classes->pluck('class_name');
    }

    /**
     * Scope to one school. The first clause of every query in this feature.
     *
     * @param  Builder<ClassNote>  $query
     */
    public function scopeForSchool(Builder $query, School|int $school): void
    {
        $query->where('school_id', $school instanceof School ? $school->id : $school);
    }

    /**
     * Scope to the notes sent to one class.
     *
     * Paired with forSchool() at every call site, never used alone: two
     * schools may both have a class called "JSS 1A", and this clause on its
     * own would hand one school's notes to the other's pupils.
     *
     * @param  Builder<ClassNote>  $query
     */
    public function scopeForClass(Builder $query, ?string $className): void
    {
        // A pupil with no class recorded receives nothing, rather than
        // everything, which is what an unfiltered query would do.
        if ($className === null || trim($className) === '') {
            $query->whereRaw('1 = 0');

            return;
        }

        $query->whereHas('classes', fn (Builder $classes) => $classes->where('class_name', $className));
    }

    /**
     * Whether this note was sent to the given class.
     *
     * The check a download makes before it streams anything to a pupil.
     */
    public function wasSentTo(?string $className): bool
    {
        if ($className === null || trim($className) === '') {
            return false;
        }

        return $this->classes()->where('class_name', $className)->exists();
    }

    /**
     * Whether the stored file is still on disk.
     */
    public function fileExists(): bool
    {
        return Storage::disk(self::DISK)->exists($this->path);
    }

    /**
     * Whether the text could be read out of the document.
     *
     * False for a legacy .doc, whose binary format PHPWord cannot open. Those
     * notes are download-only, and the pupil's page says so rather than
     * showing an empty panel.
     */
    public function isReadable(): bool
    {
        return trim((string) $this->body_text) !== '';
    }

    /**
     * The size, for the one line that tells a pupil what they are downloading.
     */
    public function readableSize(): string
    {
        $kilobytes = $this->size_bytes / 1024;

        return $kilobytes < 1024
            ? number_format(max($kilobytes, 1), 0).' KB'
            : number_format($kilobytes / 1024, 1).' MB';
    }
}
