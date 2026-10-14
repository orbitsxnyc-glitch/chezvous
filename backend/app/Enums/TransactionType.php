<?php

namespace App\Enums;

enum TransactionType: string
{
    case Sale = 'sale';
    case RentLong = 'rent_long';
    case RentFurnishedShort = 'rent_furnished_short';
}
