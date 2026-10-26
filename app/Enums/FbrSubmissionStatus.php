<?php

namespace App\Enums;

enum FbrSubmissionStatus: string
{
    case Pending = 'pending';
    case Processing = 'processing';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Failed = 'failed';
    case Unknown = 'unknown';
}
