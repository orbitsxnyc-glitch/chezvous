<?php

namespace App\Http\Controllers\Api\V1\Auth;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\RegisterRequest;
use App\Services\Auth\AccountService;
use Illuminate\Http\JsonResponse;

class RegisterController extends Controller
{
    public function __invoke(RegisterRequest $request, AccountService $accounts): JsonResponse
    {
        $accounts->register($request->validated());

        return response()->json([
            'data' => ['message' => 'Si le numéro est valide, un code de vérification vient d\'être envoyé.'],
        ], 202);
    }
}
