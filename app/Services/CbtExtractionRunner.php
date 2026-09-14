<?php

namespace App\Services;

use App\Enums\CbtDocumentUploadStatus;
use App\Models\CbtDocumentUpload;
use App\Models\CbtTestDocumentUpload;
use Illuminate\Support\Facades\Bus;

/**
 * Gets an uploaded question paper read, whether or not a queue worker exists.
 *
 * Extraction used to be pushed onto the queue and nothing else. On a server
 * with no worker running, which is exactly how production was deployed, the
 * upload was stored, the job was written, and the paper sat at "Extraction has
 * not started" for ever. Pressing "Queue it again" wrote another job that
 * nothing would run either.
 *
 * So the queue is used only when a worker is known to be alive, by its
 * heartbeat. Otherwise the paper is read by the web server itself, straight
 * after the page has been sent back, so the person uploading is not kept
 * waiting. Reading is local and takes seconds.
 *
 * And the pages that show an upload catch up on anything left behind: a paper
 * still waiting with no worker to take it is read there and then, and one that
 * has claimed to be "reading" for far longer than any document takes (the
 * process was stopped part way) is marked as interrupted so it can be tried
 * again. Between them there is no state an upload can be stuck in.
 */
class CbtExtractionRunner
{
    /**
     * How long a waiting upload is given to be picked up before a page reads
     * it itself. Long enough for the after-response run to finish first.
     */
    private const PICK_UP_AFTER_SECONDS = 15;

    /**
     * Far longer than any document takes to read. An upload still marked as
     * reading after this was interrupted.
     */
    private const INTERRUPTED_AFTER_MINUTES = 10;

    /**
     * A waiting upload this old, after a page has already tried to read it,
     * is genuinely stuck and worth telling somebody about.
     */
    private const STALLED_AFTER_MINUTES = 2;

    public function __construct(private readonly QueueWorkerHealth $queue) {}

    /**
     * Start reading an upload.
     */
    public function start(object $job): void
    {
        if ($this->queue->hasLiveWorker()) {
            Bus::dispatch($job);

            return;
        }

        Bus::dispatchAfterResponse($job);
    }

    /**
     * Read anything a missing worker left waiting, and release anything a
     * stopped process left half done. Called whenever an upload is looked at.
     */
    public function catchUp(CbtDocumentUpload|CbtTestDocumentUpload $upload, object $job): void
    {
        if ($this->queue->hasLiveWorker()) {
            return;
        }

        if ($upload->status === CbtDocumentUploadStatus::Pending
            && $upload->updated_at?->lte(now()->subSeconds(self::PICK_UP_AFTER_SECONDS))) {
            Bus::dispatchSync($job);
            $upload->refresh();

            return;
        }

        if (in_array($upload->status, [CbtDocumentUploadStatus::Processing, CbtDocumentUploadStatus::Importing], true)
            && $upload->updated_at?->lte(now()->subMinutes(self::INTERRUPTED_AFTER_MINUTES))) {
            $upload->update([
                'status' => CbtDocumentUploadStatus::Failed,
                'error_message' => 'Reading this document was interrupted before it finished. '
                    .'The file is stored safely. Press "Try extracting again" to read it again.',
            ]);
        }
    }

    /**
     * Whether an upload is still waiting long after it should have been read.
     */
    public function isStalled(CbtDocumentUpload|CbtTestDocumentUpload $upload): bool
    {
        return $upload->status === CbtDocumentUploadStatus::Pending
            && $upload->updated_at?->lte(now()->subMinutes(self::STALLED_AFTER_MINUTES)) === true;
    }
}
