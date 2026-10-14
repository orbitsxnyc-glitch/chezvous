<?php

namespace App\Enums;

enum PaymentStatus: string
{
    case Declared = 'declared';
    case Confirmed = 'confirmed';
    case Rejected = 'rejected';
}
