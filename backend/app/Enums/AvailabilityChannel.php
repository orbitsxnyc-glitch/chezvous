<?php

namespace App\Enums;

enum AvailabilityChannel: string
{
    case App = 'app';
    case Whatsapp = 'whatsapp';
    case Sms = 'sms';
    case Admin = 'admin';
    case System = 'system';
}
