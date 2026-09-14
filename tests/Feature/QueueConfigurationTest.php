<?php

use App\Jobs\ProcessCbtDocumentUpload;
use App\Jobs\ProcessCbtTestDocumentUpload;

/**
 * The longest a queued job in this application may run.
 *
 * @return array<class-string, int>
 */
function longRunningJobTimeouts(): array
{
    return [
        ProcessCbtDocumentUpload::class => (new ReflectionClass(ProcessCbtDocumentUpload::class))
            ->getDefaultProperties()['timeout'],
        ProcessCbtTestDocumentUpload::class => (new ReflectionClass(ProcessCbtTestDocumentUpload::class))
            ->getDefaultProperties()['timeout'],
    ];
}

test('the queue waits longer than the longest job before assuming it was abandoned', function () {
    // retry_after is how long the queue waits before deciding a reserved job
    // died and giving it to another worker. If it is shorter than the job's own
    // timeout, a long extraction gets picked up a second time while the first
    // worker is still reading the document, importing the same questions twice
    // and paying the extraction service for each pass.
    //
    // The framework default is 90 seconds. CBT extraction runs to 600.
    $retryAfter = config('queue.connections.database.retry_after');
    $longest = max(longRunningJobTimeouts());

    expect($retryAfter)->toBeGreaterThan(
        $longest,
        "queue.connections.database.retry_after ({$retryAfter}s) must exceed the longest job timeout ({$longest}s), "
            .'or long-running jobs will be run more than once concurrently.',
    );
});

test('every long-running job declares a timeout', function () {
    // A job with no timeout inherits the worker's, which makes the relationship
    // above impossible to reason about.
    foreach (longRunningJobTimeouts() as $job => $timeout) {
        expect($timeout)->toBeInt()->toBeGreaterThan(0, "{$job} must declare a timeout.");
    }
});
