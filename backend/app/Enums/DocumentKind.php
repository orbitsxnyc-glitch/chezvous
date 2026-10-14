<?php

namespace App\Enums;

enum DocumentKind: string
{
    case Ninea = 'ninea';
    case Rccm = 'rccm';
    case Other = 'other';
}
