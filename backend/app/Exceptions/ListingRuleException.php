<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/**
 * Règle métier violée sur une annonce : transition interdite, quota dépassé, fiche incomplète…
 */
class ListingRuleException extends RuntimeException
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
        ]), 422);
    }
}
