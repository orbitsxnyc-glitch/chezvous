<?php

namespace App\Http\Requests\Auth;

class LoginRequest extends PhoneRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'password' => ['required', 'string', 'max:128'],
            'device_name' => ['nullable', 'string', 'max:80'],
        ];
    }
}
