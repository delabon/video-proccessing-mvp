<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Uploaded = 'uploaded';
    case Failed = 'failed';
    case Processing = 'processing';
    case Complete = 'complete';

    public function label(): string
    {
        return match ($this) {
            self::Uploaded => 'Uploaded',
            self::Failed => 'Failed',
            self::Processing => 'Processing',
            self::Complete => 'Complete',
        };
    }
}
