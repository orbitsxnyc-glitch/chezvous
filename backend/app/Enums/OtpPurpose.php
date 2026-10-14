<?php

namespace App\Enums;

enum OtpPurpose: string
{
    case VerifyPhone = 'verify_phone';
    case PasswordReset = 'password_reset';
}
