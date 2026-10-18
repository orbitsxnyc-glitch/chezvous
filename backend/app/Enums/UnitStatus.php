<?php

namespace App\Enums;

enum UnitStatus: string
{
    case Draft = 'draft';
    case Active = 'active';
    case Paused = 'paused';
    case Archived = 'archived';
}
