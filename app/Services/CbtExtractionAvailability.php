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
     *
     * TWO AUDIENCES, for the same reason as
     * QueueWorkerHealth::stalledMessage(): the ScholarNest Team can start a worker
     * and should be told which command does it, and a teacher cannot and
     * should never be shown a shell command. This returned one message with
     * "php artisan queue:work" in it to both, so a teacher about to upload was
     * handed an instruction they had no way to act on.
     *
     * @param  bool  $canOperateTheServer  defaults to false, because the safe
     *                                     answer is the one with no shell
     *                                     command in it - a caller that
     *                                     forgets to say cannot leak operator
     *                                     instructions to a teacher.
     */
    public function warning(bool $canOperateTheServer = false): ?string
    {
        if ($this->hasWorker()) {
            return null;
        }

        if ($canOperateTheServer) {
            return 'The background service that reads uploaded documents is not running, so extraction will be queued and wait. '
                .'Your upload will still be stored safely. Start it with "php artisan queue:work" — in development, '
                .'"composer run dev" starts it alongside the web server.';
        }

        return 'The service that reads uploaded documents is not running at the moment, so extraction will wait in a queue. '
            .'You can still upload: your document will be stored safely and read as soon as the service is back. '
            .'If it stays this way, please let the ScholarNest Team know.';
    }
}
