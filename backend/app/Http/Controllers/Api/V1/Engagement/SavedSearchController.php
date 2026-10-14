<?php

namespace App\Http\Controllers\Api\V1\Engagement;

use App\Exceptions\InquiryRuleException;
use App\Http\Controllers\Controller;
use App\Http\Requests\Engagement\SavedSearchRequest;
use App\Models\SavedSearch;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;

class SavedSearchController extends Controller
{
    public function index(Request $request): JsonResponse
    {
        $rows = SavedSearch::where('user_id', $request->user()->id)->orderBy('id')->get()->map(fn ($s) => $this->present($s));

        return response()->json(['data' => $rows]);
    }

    public function store(SavedSearchRequest $request): JsonResponse
    {
        $user = $request->user();
        if (SavedSearch::where('user_id', $user->id)->count() >= config('chezvous.inquiries.saved_searches_max')) {
            throw new InquiryRuleException('saved_searches_limit', 'Nombre maximal de recherches enregistrées atteint.');
        }
        $v = $request->validated();
        $search = SavedSearch::create([
            'user_id' => $user->id, 'name' => $v['name'], 'criteria' => $v['criteria'], 'frequency' => $v['frequency'],
            'last_checked_at' => now(), // aucune alerte pour les annonces déjà en ligne
        ]);

        return response()->json(['data' => $this->present($search)], 201);
    }

    public function update(SavedSearchRequest $request, string $search): JsonResponse
    {
        $model = SavedSearch::where('ulid', $search)->where('user_id', $request->user()->id)->firstOrFail();
        $model->fill($request->safe()->only(['name', 'frequency', 'is_active']))->save();

        return response()->json(['data' => $this->present($model)]);
    }

    public function destroy(Request $request, string $search): JsonResponse
    {
        SavedSearch::where('ulid', $search)->where('user_id', $request->user()->id)->firstOrFail()->delete();

        return response()->json(null, 204);
    }

    /** @return array<string, mixed> */
    private function present(SavedSearch $s): array
    {
        return ['id' => $s->ulid, 'name' => $s->name, 'criteria' => $s->criteria, 'frequency' => $s->frequency->value, 'is_active' => $s->is_active, 'last_notified_at' => $s->last_notified_at];
    }
}
