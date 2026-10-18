<?php

namespace App\Enums;

enum CalendarBlockKind: string
{
    case Manual = 'manual';
    case Maintenance = 'maintenance';
    case External = 'external';
}
