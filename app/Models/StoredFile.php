<?php

namespace App\Models;

use Illuminate\Database\Eloquent\Model;
use Illuminate\Database\Eloquent\Relations\HasMany;
use Illuminate\Support\Facades\DB;

/**
 * An uploaded file held in the database, see the create_stored_files_table
 * migration for why, and App\Support\Storage\DatabaseFilesystemAdapter for how
 * the application reads and writes it through an ordinary Storage disk.
 */
class StoredFile extends Model
{
    /**
     * Raw bytes per chunk: 512KB, which is about 700KB once base64-encoded.
     *
     * Sized to the SMALLEST max_allowed_packet in common use, 1MB, what
     * XAMPP's MariaDB ships with, not the generous defaults of a managed
     * database. A 1MB chunk encodes to 1.4MB and failed there with "MySQL
     * server has gone away", and because the disk does not throw, a direct
     * Storage::put() simply returned false with nothing stored.
     */
    public const CHUNK_BYTES = 524_288;

    /** The smallest max_allowed_packet any supported database is expected to have. */
    public const MINIMUM_PACKET_BYTES = 1_048_576;

    /**
     * @var list<string>
     */
    protected $fillable = [
        'disk',
        'path',
        'mime_type',
        'size',
        'visibility',
        'chunk_count',
    ];

    /**
     * Chunks go with their file. Explicit rather than left to the foreign key,
     * which SQLite enforces only when told to.
     */
    protected static function booted(): void
    {
        static::deleting(function (StoredFile $file) {
            DB::table('stored_file_chunks')->where('stored_file_id', $file->id)->delete();
        });
    }

    protected function casts(): array
    {
        return [
            'size' => 'integer',
            'chunk_count' => 'integer',
        ];
    }

    /**
     * @return HasMany<StoredFileChunk, $this>
     */
    public function chunks(): HasMany
    {
        return $this->hasMany(StoredFileChunk::class);
    }

    public static function locate(string $disk, string $path): ?self
    {
        return self::query()->where('disk', $disk)->where('path', $path)->first();
    }

    /**
     * Write bytes into the stream `$target`, from byte $start to byte $end
     * inclusive, fetching only the chunks that hold them.
     *
     * @param  resource  $target
     */
    public function copyRangeTo($target, int $start = 0, ?int $end = null): void
    {
        $end = min($end ?? $this->size - 1, $this->size - 1);

        if ($this->size === 0 || $start > $end) {
            return;
        }

        $first = intdiv($start, self::CHUNK_BYTES);
        $last = intdiv($end, self::CHUNK_BYTES);

        // One chunk at a time, so a large file is never held in memory whole.
        for ($sequence = $first; $sequence <= $last; $sequence++) {
            $encoded = DB::table('stored_file_chunks')
                ->where('stored_file_id', $this->id)
                ->where('sequence', $sequence)
                ->value('data');

            $bytes = base64_decode((string) $encoded, true);

            if ($bytes === false) {
                throw new \RuntimeException("Stored file chunk {$sequence} of \"{$this->path}\" is damaged.");
            }

            $chunkStart = $sequence * self::CHUNK_BYTES;
            $from = $sequence === $first ? $start - $chunkStart : 0;
            $to = $sequence === $last ? $end - $chunkStart : strlen($bytes) - 1;

            fwrite($target, substr($bytes, $from, $to - $from + 1));
        }
    }
}
