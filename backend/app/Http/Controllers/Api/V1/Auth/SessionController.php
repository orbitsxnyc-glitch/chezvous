<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireTwoFactor;
use App\Http\Requests\Auth\LoginRequest;
use App\Http\Resources\UserResource;
use App\Models\User;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Illuminate\Support\Facades\Hash;
use Laravel\Sanctum\Http\Middleware\EnsureFrontendRequestsAreStateful;
use Laravel\Sanctum\PersonalAccessToken;

class SessionController extends Controller
{
    public function login(LoginRequest $request): JsonResponse
    {
        $v = $request->validated();
        $user = User::where('phone', $v['phone'])->first();

        if ($user === null || ! Hash::check($v['password'], $user->password)) {
            return response()->json(['code' => 'invalid_credentials', 'message' => 'Identifiants incorrects.'], 401);
        }
        if (! $user->isActive()) {
            return response()->json(['code' => 'account_suspended', 'message' => 'Ce compte est suspendu.'], 403);
        }
        if (! $user->hasVerifiedPhone()) {
            return response()->json(['code' => 'phone_not_verified', 'message' => 'Vérifiez votre numéro de téléphone.'], 403);
        }

        // Web : session par cookie (SPA). Mobile : jeton révocable par appareil.
        if (EnsureFrontendRequestsAreStateful::fromFrontend($request)) {
            Auth::guard('web')->login($user);
            $request->session()->regenerate();
            $user->recordAudit('user.login', null, ['channel' => 'session'], $user->id);

            return response()->json(['data' => new UserResource($user)]);
        }

        if (empty($v['device_name'])) {
            return response()->json(['code' => 'validation_error', 'message' => 'Le nom de l\'appareil est requis.', 'errors' => ['device_name' => ['Le nom de l\'appareil est requis.']]], 422);
        }

        $token = $user->createToken($v['device_name'])->plainTextToken;
        $user->recordAudit('user.login', null, ['channel' => 'token'], $user->id);

        return response()->json(['data' => ['token' => $token, 'user' => new UserResource($user)]]);
    }

    public function logout(Request $request): JsonResponse
    {
        $user = $request->user();
        app(TwoFactorService::class)->forgetVerification($user, RequireTwoFactor::contextKey($request));
        $token = $user->currentAccessToken();

        if ($token instanceof PersonalAccessToken) {
            $token->delete();
        } else {
            Auth::guard('web')->logout();
            if ($request->hasSession()) {
                $request->session()->invalidate();
                $request->session()->regenerateToken();
            }
        }
        $user->recordAudit('user.logout', null, null, $user->id);

        return response()->json(['data' => ['message' => 'Déconnecté.']]);
    }
}
