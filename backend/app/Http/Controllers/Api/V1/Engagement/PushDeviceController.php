<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Models\Device;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Validation\Rule;

/** Appareils de notification push (POST /my/devices). Le jeton est chiffré en base et ne ressort jamais. */
class PushDeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = Device::where('user_id', $request->user()->id)->whereNull('revoked_at')->latest('id')->get()
            ->map(fn (Device $d) => ['id' => $d->ulid, 'platform' => $d->platform, 'last_seen_at' => $d->last_seen_at]);

        return response()->json(['data' => $rows]);
    }

    /** Enregistre ou rafraîchit un appareil. Un jeton déjà connu change de propriétaire (l'ancien est révoqué). */
    public function store(Request $request): JsonResponse
    {
        $data = $request->validate(['platform' => ['required', Rule::in(['android', 'ios', 'web'])], 'token' => ['required', 'string', 'min:20', 'max:4096']]);
        $hash = hash('sha256', $data['token']);

        $device = Device::firstOrNew(['token_hash' => $hash]);
        $device->forceFill([
            'user_id' => $request->user()->id, 'platform' => $data['platform'], 'push_token' => $data['token'], 'token_hash' => $hash,
            'last_seen_at' => now(), 'revoked_at' => null,
        ])->save();

        return response()->json(['data' => ['id' => $device->ulid, 'platform' => $device->platform]], 201);
    }

    public function destroy(Request $request, string $device): JsonResponse
    {
        Device::where('ulid', $device)->where('user_id', $request->user()->id)->firstOrFail()->forceFill(['revoked_at' => now()])->save();

        return response()->json(null, 204);
    }
}
