<?php

namespace App\Exceptions;

use Illuminate\Http\JsonResponse;
use RuntimeException;

/** Règle métier violée sur une demande, une visite ou un favori. */
class InquiryRuleException extends RuntimeException
{
    public function __construct(public readonly string $errorCode, string $message, private readonly int $status = 422)
    {
        parent::__construct($message);
    }

    public function render(): JsonResponse
    {
        return response()->json(['code' => $this->errorCode, 'message' => $this->getMessage()], $this->status);
    }
}
