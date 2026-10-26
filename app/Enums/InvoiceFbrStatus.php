<?php

namespace App\Enums;

enum InvoiceFbrStatus: string
{
    case NotSubmitted = 'not_submitted';
    case Pending = 'pending';
    case ValidationFailed = 'validation_failed';
    case Accepted = 'accepted';
    case Rejected = 'rejected';
    case Unknown = 'unknown';
}
