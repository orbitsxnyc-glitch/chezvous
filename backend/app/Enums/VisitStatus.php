<?php

namespace App\Enums;

enum VisitStatus: string
{
    case Proposed = 'proposed';
    case Confirmed = 'confirmed';
    case Rescheduled = 'rescheduled';
    case Cancelled = 'cancelled';
    case Done = 'done';
}
