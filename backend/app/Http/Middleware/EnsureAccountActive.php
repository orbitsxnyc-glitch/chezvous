<?php

namespace App\Http\Middleware;

use App\Enums\UserStatus;
use Closure;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Auth;
use Symfony\Component\HttpFoundation\Response;

/**
 * Coupe immédiatement l'accès d'un compte suspendu ou supprimé, même avec un jeton ou une session déjà ouverts.
 * (L'appartenance et le statut sont relus à chaque requête, comme pour les agences.)
 */
class EnsureAccountActive
{
    public function handle(Request $request, Closure $next): Response
    {
        $user = $request->user('sanctum');

        if ($user !== null && $user->status !== UserStatus::Active) {
            if ($request->hasSession()) {
                Auth::guard('web')->logout();
            }

            return response()->json(['code' => 'account_suspended', 'message' => 'Ce compte est suspendu. Contactez le support.'], 403);
        }

        return $next($request);
    }
}
