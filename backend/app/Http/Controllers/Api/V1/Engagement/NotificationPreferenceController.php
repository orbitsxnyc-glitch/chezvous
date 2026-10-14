<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Services\Notifications\PreferenceService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationPreferenceController extends Controller
{
    public function __construct(private readonly PreferenceService $preferences) {}

    /** Préférences effectives par type et par canal ; les messages de sécurité sont verrouillés. */
    public function show(Request $request): JsonResponse
    {
        return response()->json(['data' => $this->preferences->describe($request->user())]);
    }

    public function update(Request $request): JsonResponse
    {
        $channels = config('notifications.channels');
        $data = $request->validate([
            'preferences' => ['required', 'array', 'min:1', 'max:60', function (string $attribute, mixed $value, \Closure $fail) use ($channels) {
                foreach ($value as $type => $changes) {
                    if (! is_array($changes) || $changes === []) {
                        return $fail("Préférences invalides pour « {$type} ».");
                    }
                    foreach ($changes as $channel => $enabled) {
                        if (! in_array($channel, $channels, true)) {
                            return $fail("Canal inconnu : {$channel}.");
                        }
                        if (! is_bool($enabled)) {
                            return $fail("La valeur du canal {$channel} doit être vrai ou faux.");
                        }
                    }
                }
            }],
        ]);
        $this->preferences->update($request->user(), $data['preferences']);

        return response()->json(['data' => $this->preferences->describe($request->user()->refresh())]);
    }
}
