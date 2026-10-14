<?php

namespace App\Enums;

enum CheckStatus: string
{
    case Passed = 'passed';
    case Failed = 'failed';
    case Pending = 'pending';
    case Expired = 'expired';
}
