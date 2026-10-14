<?php

namespace App\Enums;

enum PremiumSource: string
{
    case Payment = 'payment';
    case Credit = 'credit';
}
