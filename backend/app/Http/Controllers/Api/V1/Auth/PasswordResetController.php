<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\ResetPasswordRequest;
use App\Services\Auth\AccountService;
use Illuminate\Http\JsonResponse;

class PasswordResetController extends Controller
{
    public function __invoke(ResetPasswordRequest $request, AccountService $accounts): JsonResponse
    {
        $v = $request->validated();

        if (! $accounts->resetPassword($v['phone'], $v['code'], $v['password'])) {
            return response()->json(['code' => 'invalid_otp', 'message' => 'Code invalide ou expiré.'], 422);
        }

        return response()->json(['data' => ['message' => 'Mot de passe modifié.']]);
    }
}
