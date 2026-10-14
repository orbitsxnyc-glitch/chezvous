<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Enums\OtpPurpose;
use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\SendOtpRequest;
use App\Http\Requests\Auth\VerifyOtpRequest;
use App\Services\Auth\AccountService;
use Illuminate\Http\JsonResponse;

class OtpController extends Controller
{
    public function send(SendOtpRequest $request, AccountService $accounts): JsonResponse
    {
        $accounts->sendOtp($request->validated('phone'), OtpPurpose::from($request->validated('purpose')));

        return response()->json([
            'data' => ['message' => 'Si le numéro est valide, un code vient d\'être envoyé.'],
        ], 202);
    }

    public function verify(VerifyOtpRequest $request, AccountService $accounts): JsonResponse
    {
        if (! $accounts->verifyPhone($request->validated('phone'), $request->validated('code'))) {
            return response()->json(['code' => 'invalid_otp', 'message' => 'Code invalide ou expiré.'], 422);
        }

        return response()->json(['data' => ['message' => 'Numéro vérifié.']]);
    }
}
