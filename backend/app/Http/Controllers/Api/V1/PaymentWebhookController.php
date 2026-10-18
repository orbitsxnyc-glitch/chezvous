<?php

namespace App\Http\Controllers\Api\V1;

use App\Exceptions\StayRuleException;
use App\Http\Controllers\Controller;
use App\Services\Payments\PaymentService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use InvalidArgumentException;
use Throwable;

/**
 * Notifications des prestataires de paiement. Aucun prestataire en ligne n'est actif : toute notification est refusée
 * (404) tant qu'un adaptateur ne la prend pas en charge ; un adaptateur doit vérifier signature et horodatage avant tout effet.
 */
class PaymentWebhookController extends Controller
{
    public function __invoke(Request $request, string $provider, PaymentService $payments): JsonResponse
    {
        try {
            $payments->handleWebhook($provider, $request->getContent(), array_map(fn ($v) => (string) $v[0], $request->headers->all()));
        } catch (InvalidArgumentException|StayRuleException $e) {
            // Prestataire inconnu ou sans webhook : on ne révèle rien.
            return response()->json(['message' => 'Introuvable.'], 404);
        } catch (Throwable) {
            return response()->json(['message' => 'Notification refusée.'], 400); // signature ou contenu invalide
        }

        return response()->json(['received' => true]);
    }
}
