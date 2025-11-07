<?php

namespace App\Enums;

enum VideoStatus: string
{
    case Uploaded = 'uploaded';
    case Failed = 'failed';
    case Processing = 'processing';
    case Complete = 'complete';
}
