<?php

namespace App\Enums;

enum StockMovementType: string
{
    case Opening = 'opening';
    case Receipt = 'receipt';
    case Issue = 'issue';
    case Adjustment = 'adjustment';
    case Reversal = 'reversal';
}
