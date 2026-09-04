<?php

namespace App\Models\Concerns;

use App\Models\Signature;
use Illuminate\Database\Eloquent\Relations\MorphOne;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Storage;

/**
 * A signer: somebody who can register one signature of their own.
 *
 * One, not many - the relation is a morphOne and the table enforces it with a
 * unique index, so registering again replaces what was there rather than
 * leaving two marks with no way to tell which is current.
 *
 * @property-read Signature|null $signature
 */
trait HasSignature
{
    /**
     * @return MorphOne<Signature, $this>
     */
    public function signature(): MorphOne
    {
        return $this->morphOne(Signature::class, 'owner');
    }

    /**
     * The signature as an inline data URI - it has no URL. See
     * App\Models\Signature::dataUri() for why.
     */
    public function signatureDataUri(): ?string
    {
        return $this->signature?->dataUri();
    }

    /**
     * Absolute local filesystem path to the signature, for dompdf views only.
     */
    public function signatureAbsolutePath(): ?string
    {
        return $this->signature?->absolutePath();
    }

    /**
     * Register this signer's signature, replacing any they already had.
     *
     * The old file is deleted rather than orphaned: a signature nobody can
     * reach is still a signature sitting on disk, and the point of replacing
     * one is usually that the previous is no longer wanted.
     *
     * THE DELETE WAITS FOR THE COMMIT, and that is not a nicety. A filesystem
     * has no transaction to roll back: done inline, a registration inside a
     * transaction that later rolls back leaves the row pointing at the old
     * path and the old FILE already destroyed - a signature that exists
     * everywhere except on disk, which renders as nothing at all. That is not
     * hypothetical; it is how a real school's Principal signature was lost.
     */
    public function registerSignature(string $path): Signature
    {
        $previous = $this->signature?->path;

        $signature = $this->signature()->updateOrCreate(
            [],
            ['school_id' => $this->school_id, 'path' => $path],
        );

        if ($previous && $previous !== $path) {
            self::deleteFileAfterCommit($previous);
        }

        $this->setRelation('signature', $signature);

        return $signature;
    }

    /**
     * Remove a stored signature file once the database is sure of it.
     *
     * Outside a transaction this is an immediate delete. Inside one it is
     * deferred to the commit, and simply never happens if the work is rolled
     * back.
     */
    protected static function deleteFileAfterCommit(string $path): void
    {
        if (DB::transactionLevel() === 0) {
            Storage::disk('local')->delete($path);

            return;
        }

        DB::afterCommit(fn () => Storage::disk('local')->delete($path));
    }

    /**
     * Withdraw this signer's signature.
     *
     * Has to be possible and has to be explicit: a signature on the wrong
     * name is worse than no signature, and re-drawing over one is not a way to
     * take it back.
     */
    public function withdrawSignature(): void
    {
        $signature = $this->signature;

        if (! $signature) {
            return;
        }

        $path = $signature->path;
        $signature->delete();

        // Same reasoning as registerSignature(): the row can be rolled back,
        // the file cannot.
        self::deleteFileAfterCommit($path);

        $this->setRelation('signature', null);
    }
}
