<?php

namespace App\Enums;

enum MandateRole: string
{
    case Owner = 'owner';
    case Mandatary = 'mandatary';
    case Intermediary = 'intermediary';
}
