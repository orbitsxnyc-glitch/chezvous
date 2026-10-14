<?php

namespace App\Enums;

enum PreferredChannel: string
{
    case Phone = 'phone';
    case Whatsapp = 'whatsapp';
    case Message = 'message';
}
