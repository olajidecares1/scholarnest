<?php

namespace App\Enums;

enum CbtDocumentUploadStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case NeedsMapping = 'needs_mapping';
    case Completed = 'completed';
    case Failed = 'failed';

    public function label(): string
    {
        return match ($this) {
            self::Pending => 'Pending',
            self::Processing => 'Processing',
            self::NeedsMapping => 'Needs Exam Body / Subject',
            self::Completed => 'Completed',
            self::Failed => 'Failed',
        };
    }
}
