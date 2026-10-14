<?php

namespace App\Http\Requests\Auth;

use Illuminate\Validation\Rules\Password;

class ResetPasswordRequest extends PhoneRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:'.config('chezvous.otp.length')],
            'password' => ['required', 'string', Password::min(8)->max(128)],
        ];
    }
}
