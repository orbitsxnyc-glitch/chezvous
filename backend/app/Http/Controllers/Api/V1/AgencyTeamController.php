<?php

namespace App\Http\Controllers\Api\V1;

use App\Enums\MemberStatus;
use App\Http\Controllers\Controller;
use App\Http\Requests\Agencies\InviteMemberRequest;
use App\Http\Requests\Agencies\UpdateMemberRequest;
use App\Services\Agencies\AgencyTeamService;
use Illuminate\Http\JsonResponse;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\Gate;

class AgencyTeamController extends Controller
{
    public function __construct(private readonly AgencyTeamService $team) {}

    /** Gérant : détail complet. Agent : noms et rôles seulement (ni téléphones ni statuts). */
    public function members(Request $request): JsonResponse
    {
        Gate::authorize('agency-member');
        $me = $request->user()->activeMembership();
        $isManager = $me->isManager();

        $rows = $me->agency->members()->with('user')->orderBy('id')->get()->map(fn ($m) => array_filter([
            'id' => $m->ulid,
            'name' => $m->user->name,
            'role' => $m->role->value,
            'status' => $isManager ? $m->status->value : null,
            'phone' => $isManager ? $m->user->phone : null,
            'can_see_all' => $isManager ? $m->can_see_all : null,
            'joined_at' => $isManager ? $m->joined_at : null,
        ], fn ($v) => $v !== null));

        return response()->json(['data' => $rows]);
    }

    public function updateMember(UpdateMemberRequest $request, string $member): JsonResponse
    {
        $manager = $request->user();
        // Résolution limitée à l'agence du gérant : le membre d'une autre agence est introuvable (404).
        $target = $manager->activeMembership()->agency->members()->where('ulid', $member)->firstOrFail();
        $data = $request->validated();

        if (array_key_exists('can_see_all', $data)) {
            $this->team->setSeeAll($target, (bool) $data['can_see_all'], $manager);
        }
        if (($data['status'] ?? null) === MemberStatus::Suspended->value && $target->isActive()) {
            $this->team->suspend($target, $manager);
        } elseif (($data['status'] ?? null) === MemberStatus::Active->value && ! $target->isActive()) {
            $this->team->reinstate($target, $manager);
        }

        return response()->json(['data' => ['id' => $target->ulid, 'status' => $target->refresh()->status->value, 'can_see_all' => $target->can_see_all]]);
    }

    public function invitations(Request $request): JsonResponse
    {
        Gate::authorize('manage-agency');
        $rows = $request->user()->activeMembership()->agency->invitations()->latest('id')->get()->map(fn ($i) => [
            'id' => $i->ulid, 'phone' => $i->phone, 'email' => $i->email, 'can_see_all' => $i->can_see_all, 'expires_at' => $i->expires_at,
            'status' => $i->accepted_at ? 'accepted' : ($i->revoked_at ? 'revoked' : ($i->expires_at->isPast() ? 'expired' : 'pending')),
        ]);

        return response()->json(['data' => $rows]);
    }

    public function invite(InviteMemberRequest $request): JsonResponse
    {
        $manager = $request->user();
        $invitation = $this->team->invite($manager->activeMembership()->agency, $manager, $request->input('phone'), $request->input('email'), $request->boolean('can_see_all'));

        return response()->json(['data' => ['id' => $invitation->ulid, 'expires_at' => $invitation->expires_at]], 201);
    }

    public function revoke(Request $request, string $invitation): JsonResponse
    {
        Gate::authorize('manage-agency');
        $target = $request->user()->activeMembership()->agency->invitations()->where('ulid', $invitation)->firstOrFail();
        $this->team->revokeInvitation($target, $request->user());

        return response()->json(null, 204);
    }

    /** Acceptation par la personne invitée (connectée, sans appartenance préalable). */
    public function accept(Request $request): JsonResponse
    {
        $data = $request->validate(['token' => ['required', 'string', 'min:20', 'max:100']]);
        $member = $this->team->accept($request->user(), $data['token']);

        return response()->json(['data' => ['agency' => $member->agency->name, 'role' => $member->role->value]], 201);
    }
}
