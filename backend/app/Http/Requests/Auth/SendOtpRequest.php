<?php

namespace App\Http\Requests\Auth;

use App\Enums\OtpPurpose;
use Illuminate\Validation\Rule;

class SendOtpRequest extends PhoneRequest
{
    /**
     * @return array<string, mixed>
     */
    public function rules(): array
    {
        return [
            'phone' => ['required', 'string'],
            'purpose' => ['required', Rule::enum(OtpPurpose::class)],
        ];
    }
}
