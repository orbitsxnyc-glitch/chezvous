<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use App\Http\Requests\Auth\UpdateProfileRequest;
use App\Http\Resources\UserResource;
use App\Services\Auth\AccountService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Hash;

class MeController extends Controller
{
    public function show(Request $request): UserResource
    {
        return new UserResource($request->user());
    }

    public function update(UpdateProfileRequest $request): UserResource
    {
        $user = $request->user();
        $data = $request->validated();

        if (($data['whatsapp_same_as_phone'] ?? false) === true) {
            $data['whatsapp'] = $user->phone;
        }
        unset($data['whatsapp_same_as_phone']);

        $user->fill($data)->save();

        // Seuls les noms des champs modifiés sont journalisés, jamais leurs valeurs (données personnelles).
        $user->recordAudit('user.profile_updated', null, ['fields' => array_keys($data)]);

        return new UserResource($user->refresh());
    }

    public function destroy(Request $request, AccountService $accounts): JsonResponse
    {
        $request->validate(['password' => ['required', 'string']]);

        if (! Hash::check($request->input('password'), $request->user()->password)) {
            return response()->json(['code' => 'invalid_credentials', 'message' => 'Mot de passe incorrect.'], 403);
        }

        $accounts->delete($request->user());

        return response()->json(null, 204);
    }
}
