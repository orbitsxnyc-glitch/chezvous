<?php

namespace App\Enums;

enum PremiumStatus: string
{
    case Pending = 'pending';
    case Active = 'active';
    case Suspended = 'suspended';
    case Cancelled = 'cancelled';
    case Expired = 'expired';
}
