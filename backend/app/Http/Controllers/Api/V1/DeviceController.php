<?php

namespace App\Http\Controllers\Api\V1;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class DeviceController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $devices = $request->user()->tokens()->latest('id')->get()
            ->map(fn ($t) => ['id' => $t->id, 'name' => $t->name, 'last_used_at' => $t->last_used_at, 'created_at' => $t->created_at]);

        return response()->json(['data' => $devices]);
    }

    public function destroy(Request $request, int $id): JsonResponse
    {
        $deleted = $request->user()->tokens()->whereKey($id)->delete();

        abort_if($deleted === 0, 404);
        $request->user()->recordAudit('user.device_revoked', null, ['token_id' => $id]);

        return response()->json(null, 204);
    }
}
