<?php

namespace App\Enums;

enum PlatformRole: string
{
    case Client = 'client';
    case Particulier = 'particulier';
    case Admin = 'admin';
    case SuperAdmin = 'super_admin';
}
