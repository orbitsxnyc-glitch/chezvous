<?php

namespace App\Enums;

enum PlanAudience: string
{
    case Individual = 'individual';
    case Agency = 'agency';
    case Both = 'both';
}
