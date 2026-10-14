<?php

namespace App\Http\Middleware;

use App\Services\Auth\TwoFactorService;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;
use Laravel\Sanctum\PersonalAccessToken;
use Symfony\Component\HttpFoundation\Response;

/**
 * Aucune route d'administration n'est accessible tant que le second facteur n'est pas validé dans la session
 * (ou pour le jeton) courante. Les comptes qui ne sont pas administrateurs reçoivent 403.
 */
class RequireTwoFactor
{
    public function __construct(private readonly TwoFactorService $twoFactor) {}

    public static function contextKey(Request $request): string
    {
        $token = $request->user()?->currentAccessToken();

        return $token instanceof PersonalAccessToken ? 'token:'.$token->id : 'session:'.($request->hasSession() ? $request->session()->getId() : 'none');
    }

    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user();
        if ($user === null) {
            return $next($request);
        }
        // Le rôle est vérifié ici, avant la liaison des modèles : un non-administrateur reçoit 403 que l'identifiant existe ou non.
        if (! Gate::forUser($user)->allows('moderate')) {
            return response()->json(['code' => 'forbidden', 'message' => 'Action non autorisée.'], 403);
        }
        if (! $this->twoFactor->isRequiredFor($user)) {
            return $next($request);
        }
        if (! $user->hasTwoFactor()) {
            return response()->json(['code' => 'two_factor_setup_required', 'message' => 'La double authentification est obligatoire pour les administrateurs : activez-la.'], 403);
        }
        if (! $this->twoFactor->isVerified($user, self::contextKey($request))) {
            return response()->json(['code' => 'two_factor_required', 'message' => 'Saisissez votre code de double authentification.'], 403);
        }

        return $next($request);
    }
}
