<?php

namespace App\Enums;

enum LocationPrecision: string
{
    case Exact = 'exact';
    case Approximate = 'approximate';
    case Hidden = 'hidden';
}
