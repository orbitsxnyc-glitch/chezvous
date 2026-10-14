<?php

namespace App\Enums;

enum CheckType: string
{
    case Phone = 'phone';
    case Identity = 'identity';
    case Professional = 'professional';
    case Location = 'location';
    case Document = 'document';
    case FieldVisit = 'field_visit';
}
