<?php

namespace App\Services;

/**
 * Can a document actually be extracted right now?
 *
 * Extraction itself runs locally - PHPWord, smalot/pdfparser and a PHP parser -
 * so it needs no key, no credit and no internet. It used to depend on a hosted
 * model, and this class used to warn when that account was unconfigured. That
 * warning is gone because the dependency is gone.
 *
 * One requirement remains: a queue worker. Extraction is queued so the browser
 * is never left waiting on a 20MB PDF, and queued work only happens if
 * something is running the queue. When nothing is, the upload succeeds, the job
 * is written, and the page sits at "Pending" forever - indistinguishable, to
 * the person who uploaded it, from a broken upload. That is what this reports.
 */
class CbtExtractionAvailability
{
    public function __construct(private QueueWorkerHealth $queue) {}

    /**
     * Is anything going to run the queued job?
     */
    public function hasWorker(): bool
    {
        return $this->queue->isRunning();
    }

    public function isReady(): bool
    {
        return $this->hasWorker();
    }

    /**
     * What to tell someone before they upload, or null when nothing is wrong.
     *
     * Says the document is stored either way, because it is: a queue that is
     * not running delays extraction, it never loses the file.
     */
    public function warning(): ?string
    {
        if ($this->hasWorker()) {
            return null;
        }

        return 'The background service that reads uploaded documents is not running, so extraction will be queued and wait. '
            .'Your upload will still be stored safely. Start it with "php artisan queue:work" — in development, '
            .'"composer run dev" starts it alongside the web server.';
    }
}
