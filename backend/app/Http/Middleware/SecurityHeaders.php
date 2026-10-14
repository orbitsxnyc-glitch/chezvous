<?php

namespace App\Http\Middleware;

use Closure;
use Illuminate\Http\Request;
use Symfony\Component\HttpFoundation\Response;

/**
 * En-têtes de sécurité de l'API (spec section 8) : HSTS, anti-sniffing, anti-cadre, politique de référent et CSP stricte.
 * Une API JSON n'a aucune raison d'exécuter du contenu actif : la CSP interdit tout.
 */
class SecurityHeaders
{
    public function handle(Request $request, Closure $next): Response
    {
        $response = $next($request);
        $h = $response->headers;

        $h->set('X-Content-Type-Options', 'nosniff');
        $h->set('X-Frame-Options', 'DENY');
        $h->set('Referrer-Policy', 'strict-origin-when-cross-origin');
        $h->set('Permissions-Policy', 'camera=(), microphone=(), geolocation=(), payment=(), usb=()');
        $h->set('Content-Security-Policy', "default-src 'none'; frame-ancestors 'none'; base-uri 'none'; form-action 'none'");
        $h->set('Cross-Origin-Resource-Policy', 'same-site');

        // HSTS : seulement en HTTPS (ou en production derrière un proxy qui termine TLS).
        if ($request->isSecure() || app()->environment('production')) {
            $h->set('Strict-Transport-Security', 'max-age=31536000; includeSubDomains');
        }
        // Réponses d'un utilisateur connecté (profil, demandes, administration) : jamais mises en cache partagé.
        if ($request->user('sanctum') !== null && ! $h->has('Cache-Control')) {
            $h->set('Cache-Control', 'private, no-store');
        } elseif ($request->user('sanctum') !== null) {
            $h->set('Cache-Control', 'private, no-store');
        }

        return $response;
    }
}
