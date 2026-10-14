<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\LocalityLevel;
use App\Exceptions\AdminRuleException;
use App\Http\Controllers\Controller;
use App\Models\Amenity;
use App\Models\Locality;
use App\Models\ModerationReason;
use App\Models\PropertyType;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Support\Str;
use Illuminate\Validation\Rule;

/**
 * Référentiels (ADM-05) : localités, types de biens, équipements, motifs. On ne supprime jamais : on désactive
 * (les annonces existantes y font référence). Le code d'un référentiel ne change pas une fois créé.
 */
class ReferentialController extends Controller
{
    private const LEVELS = ['region' => 0, 'department' => 1, 'commune' => 2, 'neighborhood' => 3];

    public function __construct(private readonly AuditLogger $audit) {}

    // ------------------------------------------------------------ localités

    public function localities(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => Locality::orderBy('level')->orderBy('name')->get(['id', 'parent_id', 'level', 'name', 'slug', 'code', 'is_active'])]);
    }

    public function storeLocality(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate([
            'name' => ['required', 'string', 'min:2', 'max:100'],
            'level' => ['required', Rule::enum(LocalityLevel::class)],
            'parent_id' => ['nullable', 'integer', 'exists:localities,id'],
            'code' => ['nullable', 'string', 'size:3', 'regex:/^[A-Z]{3}$/', 'unique:localities,code'],
            'latitude' => ['required_with:longitude', 'numeric', 'between:12,16.8'],
            'longitude' => ['required_with:latitude', 'numeric', 'between:-17.7,-11.3'],
        ]);
        $this->assertHierarchy($data['level'], $data['parent_id'] ?? null);

        $locality = DB::transaction(function () use ($data, $request) {
            $l = Locality::create([
                'name' => $data['name'], 'level' => $data['level'], 'parent_id' => $data['parent_id'] ?? null, 'code' => $data['code'] ?? null,
                'slug' => $this->uniqueSlug($data['name'], $data['level']), 'is_active' => true,
            ]);
            if (isset($data['latitude'])) {
                DB::update('UPDATE localities SET centroid = ST_SetSRID(ST_MakePoint(?, ?), 4326)::geography WHERE id = ?', [$data['longitude'], $data['latitude'], $l->id]);
            }
            $this->audit->record('referential.created', null, null, ['kind' => 'locality', 'slug' => $l->slug], $request->user()->id);

            return $l;
        });

        return response()->json(['data' => $locality->only(['id', 'parent_id', 'level', 'name', 'slug', 'code', 'is_active'])], 201);
    }

    public function updateLocality(Request $request, Locality $locality): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['name' => ['sometimes', 'string', 'min:2', 'max:100'], 'is_active' => ['sometimes', 'boolean'], 'code' => ['sometimes', 'nullable', 'size:3', 'regex:/^[A-Z]{3}$/', Rule::unique('localities', 'code')->ignore($locality->id)]]);
        // Le code sert à construire la référence DK-YOF-000123 : on ne le change plus une fois posé.
        if (array_key_exists('code', $data) && $locality->code !== null && $data['code'] !== $locality->code) {
            throw new AdminRuleException('immutable_code', 'Le code d\'une localité ne peut plus être modifié : il compose les références des biens.');
        }
        $locality->fill($data)->save();
        $this->audit->record('referential.updated', null, null, ['kind' => 'locality', 'slug' => $locality->slug, 'fields' => array_keys($data)], $request->user()->id);

        return response()->json(['data' => $locality->only(['id', 'parent_id', 'level', 'name', 'slug', 'code', 'is_active'])]);
    }

    // ------------------------------------------------------------ types, équipements, motifs

    public function types(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => PropertyType::orderBy('sort')->get(['id', 'code', 'label', 'sort', 'is_active'])]);
    }

    public function storeType(Request $request): JsonResponse
    {
        return $this->createSimple($request, PropertyType::class, 'property_type', 'property_types');
    }

    public function updateType(Request $request, PropertyType $type): JsonResponse
    {
        return $this->updateSimple($request, $type, 'property_type');
    }

    public function amenities(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => Amenity::orderBy('sort')->get(['id', 'code', 'label', 'sort', 'is_active'])]);
    }

    public function storeAmenity(Request $request): JsonResponse
    {
        return $this->createSimple($request, Amenity::class, 'amenity', 'amenities');
    }

    public function updateAmenity(Request $request, Amenity $amenity): JsonResponse
    {
        return $this->updateSimple($request, $amenity, 'amenity');
    }

    public function reasons(): JsonResponse
    {
        Gate::authorize('moderate');

        return response()->json(['data' => ModerationReason::orderBy('kind')->orderBy('code')->get(['id', 'code', 'label', 'kind', 'is_active'])]);
    }

    public function storeReason(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate([
            'code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', 'unique:moderation_reasons,code'], 'label' => ['required', 'string', 'min:3', 'max:120'],
            'kind' => ['required', Rule::in(['rejection', 'report'])],
        ]);
        $reason = ModerationReason::create($data + ['is_active' => true]);
        $this->audit->record('referential.created', null, null, ['kind' => 'moderation_reason', 'code' => $reason->code], $request->user()->id);

        return response()->json(['data' => $reason->only(['id', 'code', 'label', 'kind', 'is_active'])], 201);
    }

    public function updateReason(Request $request, ModerationReason $reason): JsonResponse
    {
        return $this->updateSimple($request, $reason, 'moderation_reason');
    }

    // ------------------------------------------------------------ communs

    /** @param class-string<PropertyType|Amenity> $model */
    private function createSimple(Request $request, string $model, string $kind, string $table): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['code' => ['required', 'string', 'max:40', 'regex:/^[a-z0-9_]+$/', "unique:{$table},code"], 'label' => ['required', 'string', 'min:2', 'max:100'], 'sort' => ['sometimes', 'integer', 'min:0', 'max:1000']]);
        $row = $model::create($data + ['is_active' => true]);
        $this->audit->record('referential.created', null, null, ['kind' => $kind, 'code' => $row->code], $request->user()->id);

        return response()->json(['data' => $row->only(['id', 'code', 'label', 'sort', 'is_active'])], 201);
    }

    private function updateSimple(Request $request, PropertyType|Amenity|ModerationReason $row, string $kind): JsonResponse
    {
        Gate::authorize('moderate');
        // Le code est immuable : il n'est pas dans les champs modifiables (toute tentative est ignorée par la validation).
        $data = $request->validate(['label' => ['sometimes', 'string', 'min:2', 'max:120'], 'is_active' => ['sometimes', 'boolean'], 'sort' => ['sometimes', 'integer', 'min:0', 'max:1000']]);
        $row->fill($data)->save();
        $this->audit->record('referential.updated', null, null, ['kind' => $kind, 'code' => $row->code, 'fields' => array_keys($data)], $request->user()->id);

        return response()->json(['data' => $row->only(['id', 'code', 'label', 'is_active'])]);
    }

    private function assertHierarchy(string $level, ?int $parentId): void
    {
        $rank = self::LEVELS[$level];
        if ($rank === 0 && $parentId !== null) {
            throw new AdminRuleException('invalid_parent', 'Une région n\'a pas de parent.');
        }
        if ($rank > 0) {
            $parent = $parentId ? Locality::find($parentId) : null;
            if ($parent === null || self::LEVELS[$parent->level->value] >= $rank) {
                throw new AdminRuleException('invalid_parent', 'Le parent doit être d\'un niveau supérieur (région, département, commune, quartier).');
            }
        }
    }

    private function uniqueSlug(string $name, string $level): string
    {
        $base = Str::slug($level === 'neighborhood' ? $name : $name.'-'.$level);
        $slug = $base;
        for ($i = 2; Locality::where('slug', $slug)->exists(); $i++) {
            $slug = $base.'-'.$i;
        }

        return $slug;
    }
}
