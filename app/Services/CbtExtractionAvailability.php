<?php

namespace App\Services;

/**
 * Can a document actually be extracted right now?
 *
 * Extraction runs locally, PHPWord, smalot/pdfparser and a PHP parser, so it
 * needs no key, no credit and no internet.
 *
 * It no longer needs a queue worker either. It used to, and this class warned
 * when none was running, because an upload would then sit waiting for ever.
 * CbtExtractionRunner now reads the document on the web server whenever no
 * worker is alive, so there is nothing left to warn about before an upload.
 */
class CbtExtractionAvailability
{
    public function __construct(private QueueWorkerHealth $queue) {}

    /**
     * Whether a queue worker is running. Informational only: extraction works
     * either way.
     */
    public function hasWorker(): bool
    {
        return $this->queue->hasLiveWorker();
    }

    public function isReady(): bool
    {
        return true;
    }

    /**
     * What to tell someone before they upload, or null when nothing is wrong.
     *
     * @param  bool  $canOperateTheServer  kept so callers that distinguish the
     *                                     two audiences need not change.
     */
    public function warning(bool $canOperateTheServer = false): ?string
    {
        return null;
    }
}
