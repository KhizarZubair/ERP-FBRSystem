<?php

namespace App\Enums;

enum InvoiceStatus: string
{
    case Draft = 'draft';
    case Approved = 'approved';
    case Issued = 'issued';
    case Cancelled = 'cancelled';
}
