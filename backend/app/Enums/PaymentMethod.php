<?php

namespace App\Enums;

enum PaymentMethod: string
{
    case Wave = 'wave';
    case OrangeMoney = 'orange_money';
    case Transfer = 'transfer';
    case Cash = 'cash';
    case Other = 'other';
}
