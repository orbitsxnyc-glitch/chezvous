<?php

namespace App\Enums;

enum AvailabilityResponse: string
{
    case Available = 'available';
    case Reserved = 'reserved';
    case Rented = 'rented';
    case Withdrawn = 'withdrawn';
}
