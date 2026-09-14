<?php

namespace App\Support\Storage;

use App\Models\StoredFile;
use Illuminate\Support\Facades\DB;
use League\Flysystem\Config;
use League\Flysystem\DirectoryAttributes;
use League\Flysystem\FileAttributes;
use League\Flysystem\FilesystemAdapter;
use League\Flysystem\UnableToCopyFile;
use League\Flysystem\UnableToMoveFile;
use League\Flysystem\UnableToReadFile;
use League\Flysystem\UnableToRetrieveMetadata;
use League\Flysystem\UnableToWriteFile;
use Throwable;

/**
 * A Flysystem adapter whose files live in the database.
 *
 * Registered as the "database" Storage driver, so every Storage::disk() call
 * in the application, put, get, exists, delete, response(), download(),
 * readStream, works against it unchanged. See DatabaseStorageFallback for
 * when a disk uses it.
 */
class DatabaseFilesystemAdapter implements FilesystemAdapter
{
    /**
     * @param  string  $disk  The application disk this adapter stands in for.
     * @param  string|null  $url  Base address for public files, or null for a
     *                            private disk that must never have one.
     */
    public function __construct(
        private readonly string $disk,
        private readonly ?string $url = null,
        private readonly string $defaultVisibility = 'private',
    ) {}

    public function fileExists(string $path): bool
    {
        return $this->query()->where('path', $path)->exists();
    }

    public function directoryExists(string $path): bool
    {
        return $this->query()->where('path', 'like', $this->prefix($path).'%')->exists();
    }

    public function write(string $path, string $contents, Config $config): void
    {
        $stream = fopen('php://temp', 'w+b');
        fwrite($stream, $contents);
        rewind($stream);

        try {
            $this->writeStream($path, $stream, $config);
        } finally {
            fclose($stream);
        }
    }

    public function writeStream(string $path, $contents, Config $config): void
    {
        try {
            DB::transaction(function () use ($path, $contents, $config) {
                $this->query()->where('path', $path)->get()->each->delete();

                $file = StoredFile::create([
                    'disk' => $this->disk,
                    'path' => $path,
                    'visibility' => $config->get('visibility', $this->defaultVisibility),
                ]);

                $size = 0;
                $sequence = 0;
                $head = '';

                while (! feof($contents)) {
                    $chunk = $this->readExactly($contents, StoredFile::CHUNK_BYTES);

                    if ($chunk === '') {
                        break;
                    }

                    if ($sequence === 0) {
                        $head = substr($chunk, 0, 4096);
                    }

                    DB::table('stored_file_chunks')->insert([
                        'stored_file_id' => $file->id,
                        'sequence' => $sequence++,
                        'data' => base64_encode($chunk),
                    ]);

                    $size += strlen($chunk);
                }

                $file->update([
                    'size' => $size,
                    'chunk_count' => $sequence,
                    'mime_type' => $this->detectMimeType($path, $head),
                ]);
            });
        } catch (Throwable $e) {
            throw UnableToWriteFile::atLocation($path, $e->getMessage(), $e);
        }
    }

    public function read(string $path): string
    {
        $stream = $this->readStream($path);

        try {
            return (string) stream_get_contents($stream);
        } finally {
            fclose($stream);
        }
    }

    public function readStream(string $path)
    {
        $file = $this->find($path) ?? throw UnableToReadFile::fromLocation($path, 'File not found.');

        // php://temp keeps the first 2MB in memory and spills the rest to a
        // temporary file, so a large document does not exhaust memory.
        $stream = fopen('php://temp', 'w+b');

        try {
            $file->copyRangeTo($stream);
        } catch (Throwable $e) {
            fclose($stream);

            throw UnableToReadFile::fromLocation($path, $e->getMessage(), $e);
        }

        rewind($stream);

        return $stream;
    }

    public function delete(string $path): void
    {
        $this->query()->where('path', $path)->get()->each->delete();
    }

    public function deleteDirectory(string $path): void
    {
        $this->query()->where('path', 'like', $this->prefix($path).'%')->get()->each->delete();
    }

    public function createDirectory(string $path, Config $config): void
    {
        // Directories are implied by paths, as they are in object storage.
    }

    public function setVisibility(string $path, string $visibility): void
    {
        $this->query()->where('path', $path)->update(['visibility' => $visibility]);
    }

    public function visibility(string $path): FileAttributes
    {
        $file = $this->find($path) ?? throw UnableToRetrieveMetadata::visibility($path, 'File not found.');

        return new FileAttributes($path, visibility: $file->visibility);
    }

    public function mimeType(string $path): FileAttributes
    {
        $file = $this->find($path) ?? throw UnableToRetrieveMetadata::mimeType($path, 'File not found.');

        if (! $file->mime_type) {
            throw UnableToRetrieveMetadata::mimeType($path, 'Unknown type.');
        }

        return new FileAttributes($path, mimeType: $file->mime_type);
    }

    public function lastModified(string $path): FileAttributes
    {
        $file = $this->find($path) ?? throw UnableToRetrieveMetadata::lastModified($path, 'File not found.');

        return new FileAttributes($path, lastModified: $file->updated_at?->getTimestamp());
    }

    public function fileSize(string $path): FileAttributes
    {
        $file = $this->find($path) ?? throw UnableToRetrieveMetadata::fileSize($path, 'File not found.');

        return new FileAttributes($path, $file->size);
    }

    public function listContents(string $path, bool $deep): iterable
    {
        $prefix = $this->prefix($path);
        $directories = [];

        $files = $this->query()
            ->when($prefix !== '', fn ($query) => $query->where('path', 'like', $prefix.'%'))
            ->orderBy('path')
            ->get(['path', 'size', 'mime_type', 'visibility', 'updated_at']);

        foreach ($files as $file) {
            $relative = substr($file->path, strlen($prefix));

            if (! $deep && str_contains($relative, '/')) {
                $directory = $prefix.strstr($relative, '/', true);

                if (! isset($directories[$directory])) {
                    $directories[$directory] = true;

                    yield new DirectoryAttributes($directory);
                }

                continue;
            }

            if ($deep) {
                $parts = explode('/', $relative);
                array_pop($parts);
                $walked = rtrim($prefix, '/');

                foreach ($parts as $part) {
                    $walked = ltrim($walked.'/'.$part, '/');

                    if (! isset($directories[$walked])) {
                        $directories[$walked] = true;

                        yield new DirectoryAttributes($walked);
                    }
                }
            }

            yield new FileAttributes($file->path, $file->size, $file->visibility, $file->updated_at?->getTimestamp(), $file->mime_type);
        }
    }

    public function move(string $source, string $destination, Config $config): void
    {
        try {
            $this->copy($source, $destination, $config);
            $this->delete($source);
        } catch (Throwable $e) {
            throw UnableToMoveFile::fromLocationTo($source, $destination, $e);
        }
    }

    public function copy(string $source, string $destination, Config $config): void
    {
        try {
            $stream = $this->readStream($source);
            $this->writeStream($destination, $stream, $config);
            fclose($stream);
        } catch (Throwable $e) {
            throw UnableToCopyFile::fromLocationTo($source, $destination, $e);
        }
    }

    /**
     * The browser address of a public file, used by Storage::disk()->url().
     */
    public function getUrl(string $path): string
    {
        if ($this->url === null) {
            throw new \RuntimeException("The \"{$this->disk}\" disk is private: its files have no address.");
        }

        return rtrim($this->url, '/').'/'.implode('/', array_map('rawurlencode', explode('/', ltrim($path, '/'))));
    }

    private function query()
    {
        return StoredFile::query()->where('disk', $this->disk);
    }

    private function find(string $path): ?StoredFile
    {
        return $this->query()->where('path', $path)->first();
    }

    private function prefix(string $path): string
    {
        $path = trim($path, '/');

        return $path === '' ? '' : $path.'/';
    }

    /**
     * Read up to $length bytes, looping: a stream may return fewer than asked.
     *
     * @param  resource  $stream
     */
    private function readExactly($stream, int $length): string
    {
        $buffer = '';

        while (strlen($buffer) < $length && ! feof($stream)) {
            $read = fread($stream, $length - strlen($buffer));

            if ($read === false || $read === '') {
                break;
            }

            $buffer .= $read;
        }

        return $buffer;
    }

    private function detectMimeType(string $path, string $head): ?string
    {
        $detected = $head !== '' ? (new \finfo(FILEINFO_MIME_TYPE))->buffer($head) : false;

        if ($detected && $detected !== 'application/octet-stream' && $detected !== 'application/zip') {
            return $detected;
        }

        return match (strtolower(pathinfo($path, PATHINFO_EXTENSION))) {
            'jpg', 'jpeg' => 'image/jpeg',
            'png' => 'image/png',
            'webp' => 'image/webp',
            'gif' => 'image/gif',
            'ico' => 'image/x-icon',
            'pdf' => 'application/pdf',
            'docx' => 'application/vnd.openxmlformats-officedocument.wordprocessingml.document',
            'doc' => 'application/msword',
            'mp4' => 'video/mp4',
            'mov' => 'video/quicktime',
            'webm' => 'video/webm',
            default => $detected ?: null,
        };
    }
}
