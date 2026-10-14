<?php

namespace App\Enums;

enum InquiryStatus: string
{
    case New = 'new';
    case InProgress = 'in_progress';
    case VisitScheduled = 'visit_scheduled';
    case Concluded = 'concluded';
    case Lost = 'lost';
    case Closed = 'closed';
}
