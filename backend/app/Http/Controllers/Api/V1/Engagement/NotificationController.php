<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Http\Controllers\Controller;
use App\Models\UserNotification;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class NotificationController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $page = UserNotification::where('user_id', $request->user()->id)->orderByDesc('id')->cursorPaginate(30)->withQueryString();

        return response()->json([
            'data' => collect($page->items())->map(fn (UserNotification $n) => ['id' => $n->ulid, 'type' => $n->type, 'data' => $n->data, 'read' => $n->read_at !== null, 'created_at' => $n->created_at]),
            'meta' => ['next_cursor' => $page->nextCursor()?->encode(), 'unread' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count()],
        ]);
    }

    /** Marque comme lues les notifications indiquées, ou toutes si aucun identifiant n'est donné. */
    public function read(Request $request): JsonResponse
    {
        $data = $request->validate(['ids' => ['sometimes', 'array', 'max:100'], 'ids.*' => ['string', 'max:40']]);

        UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')
            ->when(isset($data['ids']), fn ($q) => $q->whereIn('ulid', $data['ids']))->update(['read_at' => now()]);

        return response()->json(['data' => ['unread' => UserNotification::where('user_id', $request->user()->id)->whereNull('read_at')->count()]]);
    }
}
