<?php

use Illuminate\Database\Migrations\Migration;
use Illuminate\Support\Facades\Storage;

/**
 * Photographs of people and every signature move off the public disk.
 *
 * They were public FILES: /storage/students/<uuid>.jpg, no access control at
 * all. The name was unguessable and that was the whole protection, so anyone
 * who ever obtained an address, from a shared screenshot, a referrer header, a
 * cached page, could fetch that child's photograph for ever, from any network,
 * signed in or not.
 *
 * The database columns do not change. They hold a path relative to a disk, and
 * only the disk moved, which is why nothing had to be rewritten row by row.
 *
 * THE FILES ARE MOVED, NOT COPIED. A copy left behind on the public disk would
 * still be served at its old address, and this migration would have achieved
 * precisely nothing.
 */
return new class extends Migration
{
    /**
     * Directories that hold a person, or a mark made by one.
     *
     * Deliberately not everything on the public disk: school logos, gallery
     * images, news pictures, hero slides, facility photographs and ID card
     * template artwork are published on a school's own website on purpose and
     * belong exactly where they are.
     *
     * @var list<string>
     */
    private const PRIVATE_DIRECTORIES = [
        'students',
        'staff',
        'guardians',
        'users',
        'staff-signatures',
        'admin-signatures',
        'signatures',
    ];

    public function up(): void
    {
        $this->move(from: 'public', to: 'local');
    }

    /**
     * Reversible, because a rollback that stranded every photograph would make
     * this migration frightening to run, and a migration people are afraid of
     * is one that does not get run.
     */
    public function down(): void
    {
        $this->move(from: 'local', to: 'public');
    }

    private function move(string $from, string $to): void
    {
        $source = Storage::disk($from);
        $target = Storage::disk($to);

        foreach (self::PRIVATE_DIRECTORIES as $directory) {
            if (! $source->exists($directory)) {
                continue;
            }

            foreach ($source->allFiles($directory) as $path) {
                // Anything already at the destination is left alone rather than
                // overwritten: re-running this must not replace a newer file
                // with an older copy of itself.
                if (! $target->exists($path)) {
                    $target->put($path, $source->get($path));
                }

                $source->delete($path);
            }

            $source->deleteDirectory($directory);
        }
    }
};
