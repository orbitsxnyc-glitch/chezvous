<?php

namespace App\Enums;

enum ImageStatus: string
{
    case Processing = 'processing';
    case Ready = 'ready';
    case Failed = 'failed';
}
