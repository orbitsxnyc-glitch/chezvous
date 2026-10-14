<?php

namespace App\Http\Requests\Auth;

class VerifyOtpRequest extends PhoneRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'code' => ['required', 'string', 'digits:'.config('chezvous.otp.length')],
        ];
    }
}
