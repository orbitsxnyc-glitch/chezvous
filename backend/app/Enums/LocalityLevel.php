<?php

namespace App\Enums;

enum LocalityLevel: string
{
    case Region = 'region';
    case Department = 'department';
    case Commune = 'commune';
    case Neighborhood = 'neighborhood';
}
