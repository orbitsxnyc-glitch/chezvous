<?php

namespace App\Enums;

enum ReportStatus: string
{
    case Open = 'open';
    case Dismissed = 'dismissed';
    case Actioned = 'actioned';
}
