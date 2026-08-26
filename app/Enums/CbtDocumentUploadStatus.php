<?php

namespace App\Enums;

enum CbtDocumentUploadStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Importing = 'importing';
    case NeedsMapping = 'needs_mapping';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::Importing => 'Creating CBT',
            self::NeedsMapping => 'Needs Exam Body / Subject',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }

    /**
     * Is this upload still going somewhere?
     *
     * The progress interface polls while this is true and stops when it is
     * not, so it has to be the single answer to "are we done" rather than a
     * list of statuses repeated in a view.
     */
    public function isInProgress(): bool
    {
        return in_array($this, [self::Pending, self::Processing, self::Importing], true);
    }

    /**
     * What the person watching should be told is happening.
     *
     * Pending is deliberately not called "queued": that is our word for it,
     * and what it means to them is that nothing has started yet.
     */
    public function progressMessage(): string
    {
        return match ($this) {
            self::Pending => 'Waiting to start…',
            self::Processing => 'Reading the document and extracting questions…',
            self::Importing => 'Creating the CBT from the extracted questions…',
            self::NeedsMapping => 'Extracted — needs an exam body and subject',
            self::Completed => 'Questions imported successfully',
            self::Failed => 'Extraction failed',
        };
    }

    /**
     * Percentage where it is honestly known, null where it is not.
     *
     * Processing has no measurable progress - the extractor does not report
     * how far through a document it is - so this returns null and the bar
     * animates rather than inventing a number that would reach 100% while the
     * work was still running.
     */
    public function progressPercent(): ?int
    {
        return match ($this) {
            self::Pending => 0,
            self::Processing, self::Importing => null,
            default => 100,
        };
    }
}
