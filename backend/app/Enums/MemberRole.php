<?php

namespace App\Enums;

enum MemberRole: string
{
    case Manager = 'manager';
    case Agent = 'agent';
}
