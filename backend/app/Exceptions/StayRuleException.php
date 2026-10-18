<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Règle métier violée sur un séjour : dates invalides, unité indisponible, tarif absent…
 * `UNAVAILABLE` est présenté en 409 (conflit de dates), le reste en 422.
 */
class StayRuleException extends RuntimeException
{
    /**
     * @param  array<string, mixed>  $details
     */
    public function __construct(public readonly string $errorCode, string $message, public readonly array $details = [])
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(array_filter([
            'code' => $this->errorCode,
            'message' => $this->getMessage(),
            'details' => $this->details ?: null,
        ]), $this->errorCode === 'unavailable' ? 409 : 422);
    }
}
