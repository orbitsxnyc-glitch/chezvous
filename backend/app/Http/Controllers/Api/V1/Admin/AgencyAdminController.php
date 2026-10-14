<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Enums\AgencyStatus;
use App\Exceptions\AdminRuleException;
use App\Http\Controllers\Controller;
use App\Models\Agency;
use App\Models\AuditLog;
use App\Models\Listing;
use App\Services\Audit\AuditLogger;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;
use Illuminate\Validation\Rule;

/** Gestion des agences (ADM-03) : recherche, suspension, retrait du badge de vérification, historique. */
class AgencyAdminController extends Controller
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function index(Request $request): JsonResponse
    {
        Gate::authorize('moderate');
        $request->validate(['q' => ['sometimes', 'string', 'max:100'], 'status' => ['sometimes', Rule::enum(AgencyStatus::class)], 'verified' => ['sometimes', 'boolean']]);
        $q = (string) $request->query('q', '');
        $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $q).'%';

        $page = Agency::withCount(['members', 'listings'])
            ->when($q !== '', fn ($w) => $w->where(fn ($x) => $x->where('name', 'ilike', $term)->orWhere('slug', 'ilike', $term)))
            ->when($request->query('status'), fn ($w, $v) => $w->where('status', $v))
            ->when($request->has('verified'), fn ($w) => $request->boolean('verified') ? $w->whereNotNull('verified_at') : $w->whereNull('verified_at'))
            ->orderByDesc('id')->cursorPaginate(30)->withQueryString();

        return response()->json(['data' => collect($page->items())->map(fn (Agency $a) => $this->present($a)), 'meta' => ['next_cursor' => $page->nextCursor()?->encode()]]);
    }

    public function show(Agency $agency): JsonResponse
    {
        Gate::authorize('moderate');
        $agency->loadCount(['members', 'listings']);

        return response()->json(['data' => $this->present($agency) + [
            'listings' => Listing::where('agency_id', $agency->id)->selectRaw('status, count(*) as n')->groupBy('status')->pluck('n', 'status'),
            'history' => AuditLog::where('subject_type', 'Agency')->where('subject_id', $agency->ulid)->latest('id')->limit(30)->get(['action', 'actor_id', 'after', 'created_at']),
        ]]);
    }

    /** Suspension : l'agence et ses annonces disparaissent du public (rien n'est supprimé) ; ses membres perdent l'accès à l'agence. */
    public function suspend(Request $request, Agency $agency): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        $this->assertStatus($agency, AgencyStatus::Active);
        $agency->forceFill(['status' => AgencyStatus::Suspended])->save();
        $this->audit->record('agency.suspended', $agency, ['status' => 'active'], ['status' => 'suspended', 'reason' => $data['reason']], $request->user()->id);

        return response()->json(['data' => $this->present($agency)]);
    }

    public function reactivate(Request $request, Agency $agency): JsonResponse
    {
        Gate::authorize('moderate');
        $this->assertStatus($agency, AgencyStatus::Suspended);
        $agency->forceFill(['status' => AgencyStatus::Active])->save();
        $this->audit->record('agency.reactivated', $agency, ['status' => 'suspended'], ['status' => 'active'], $request->user()->id);

        return response()->json(['data' => $this->present($agency)]);
    }

    /** Retire le badge « Agence vérifiée » (motif obligatoire) et enregistre un contrôle « professionnel » échoué. */
    public function revokeVerification(Request $request, Agency $agency): JsonResponse
    {
        Gate::authorize('moderate');
        $data = $request->validate(['reason' => ['required', 'string', 'min:3', 'max:500']]);
        if (! $agency->isVerified()) {
            throw new AdminRuleException('invalid_transition', 'Cette agence n\'est pas vérifiée.');
        }
        DB::transaction(function () use ($agency, $request, $data) {
            $agency->forceFill(['verified_at' => null])->save();
            DB::table('verification_checks')->insert([
                'subject_type' => 'agency', 'subject_id' => $agency->id, 'check_type' => 'professional', 'status' => 'failed', 'performed_by' => $request->user()->id,
                'performed_at' => now(), 'note' => $data['reason'], 'created_at' => now(), 'updated_at' => now(),
            ]);
            $this->audit->record('agency.verification_revoked', $agency, null, ['reason' => $data['reason']], $request->user()->id);
        });

        return response()->json(['data' => $this->present($agency)]);
    }

    private function assertStatus(Agency $agency, AgencyStatus $expected): void
    {
        if ($agency->status !== $expected) {
            throw new AdminRuleException('invalid_transition', "L'agence est « {$agency->status->value} ».");
        }
    }

    /** @return array<string, mixed> */
    private function present(Agency $a): array
    {
        return ['id' => $a->ulid, 'name' => $a->name, 'slug' => $a->slug, 'status' => $a->status->value, 'verified' => $a->isVerified(), 'verified_at' => $a->verified_at,
            'members' => $a->members_count ?? null, 'listings' => $a->listings_count ?? null, 'created_at' => $a->created_at];
    }
}
