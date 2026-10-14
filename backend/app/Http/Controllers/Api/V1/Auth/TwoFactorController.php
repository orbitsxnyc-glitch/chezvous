<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Exceptions\AdminRuleException;
use App\Http\Controllers\Controller;
use App\Http\Middleware\RequireTwoFactor;
use App\Services\Auth\TwoFactorService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class TwoFactorController extends Controller
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public function status(Request $request): JsonResponse
    {
        $user = $request->user();

        return response()->json(['data' => [
            'required' => $this->twoFactor->isRequiredFor($user),
            'enabled' => $user->hasTwoFactor(),
            'verified' => $user->hasTwoFactor() && $this->twoFactor->isVerified($user, RequireTwoFactor::contextKey($request)),
            'recovery_codes_left' => $this->twoFactor->recoveryCodesLeft($user),
        ]]);
    }

    /** Génère un secret à enregistrer dans l'application d'authentification (affiché une seule fois). */
    public function setup(Request $request): JsonResponse
    {
        $this->assertEligible($request);

        return response()->json(['data' => $this->twoFactor->setup($request->user())]);
    }

    /** Active la double authentification avec un premier code, et remet les codes de secours (une seule fois). */
    public function confirm(Request $request): JsonResponse
    {
        $this->assertEligible($request);
        $data = $request->validate(['code' => ['required', 'string', 'size:6']]);
        $codes = $this->twoFactor->confirm($request->user(), $data['code']);
        // Celui qui vient de prouver la possession de l'appareil est vérifié pour cette session.
        $this->twoFactor->markVerified($request->user(), RequireTwoFactor::contextKey($request));

        return response()->json(['data' => ['recovery_codes' => $codes, 'notice' => 'Conservez ces codes de secours en lieu sûr : ils ne seront plus affichés.']], 201);
    }

    /** Valide la session ou le jeton courant avec un code de l'application ou un code de secours. */
    public function verify(Request $request): JsonResponse
    {
        $data = $request->validate(['code' => ['nullable', 'string', 'size:6'], 'recovery_code' => ['nullable', 'string', 'max:20']]);
        if (empty($data['code']) && empty($data['recovery_code'])) {
            throw new AdminRuleException('invalid_code', 'Saisissez un code.');
        }
        $this->twoFactor->verify($request->user(), $data['code'] ?? null, $data['recovery_code'] ?? null, RequireTwoFactor::contextKey($request));

        return response()->json(['data' => ['verified' => true, 'recovery_codes_left' => $this->twoFactor->recoveryCodesLeft($request->user())]]);
    }

    private function assertEligible(Request $request): void
    {
        if (! $request->user()->hasAnyRole(['admin', 'super_admin'])) {
            throw new AdminRuleException('forbidden', 'Réservé aux administrateurs.', 403);
        }
    }
}
