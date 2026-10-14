<?php

namespace App\Services\Agencies;

use App\Contracts\InvitationSender;
use App\Enums\AgencyStatus;
use App\Enums\MemberRole;
use App\Enums\MemberStatus;
use App\Exceptions\AgencyRuleException;
use App\Models\Agency;
use App\Models\AgencyInvitation;
use App\Models\AgencyMember;
use App\Models\Inquiry;
use App\Models\Listing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Support\PhoneNumber;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Str;

class AgencyTeamService
{
    public function __construct(private readonly InvitationSender $sender, private readonly AuditLogger $audit) {}

    public function manager(Agency $agency): AgencyMember
    {
        return $agency->members()->where('role', MemberRole::Manager->value)->firstOrFail();
    }

    /** Invite un agent par téléphone ou e-mail. Le jeton n'est jamais stocké en clair. */
    public function invite(Agency $agency, User $manager, ?string $phone, ?string $email, bool $canSeeAll = false): AgencyInvitation
    {
        $normalized = PhoneNumber::normalize($phone);
        if ($normalized === null && $email === null) {
            throw new AgencyRuleException('invitation_target_required', 'Indiquez un numéro de téléphone sénégalais valide ou une adresse e-mail.');
        }
        if ($normalized !== null && User::where('phone', $normalized)->whereHas('agencyMembership')->exists()) {
            throw new AgencyRuleException('already_in_agency', 'Cette personne appartient déjà à une agence.');
        }

        $token = Str::random(48);
        $invitation = AgencyInvitation::create([
            'agency_id' => $agency->id, 'phone' => $normalized, 'email' => $email, 'can_see_all' => $canSeeAll,
            'token_hash' => hash('sha256', $token), 'invited_by' => $manager->id,
            'expires_at' => now()->addDays(config('chezvous.agencies.invitation_ttl_days')),
        ]);
        $this->audit->record('agency.invitation_created', $agency, null, ['invitation' => $invitation->ulid], $manager->id);

        $link = rtrim(config('chezvous.agencies.frontend_url'), '/').'/invitation/'.$token;
        $this->sender->send($normalized, $email, $agency->name, $link);

        return $invitation;
    }

    /**
     * Acceptation par un utilisateur connecté. Réponse volontairement générique pour toute invitation inutilisable.
     */
    public function accept(User $user, string $token): AgencyMember
    {
        $invalid = new AgencyRuleException('invalid_invitation', 'Invitation invalide, expirée ou déjà utilisée.');

        return DB::transaction(function () use ($user, $token, $invalid) {
            $invitation = AgencyInvitation::where('token_hash', hash('sha256', $token))->lockForUpdate()->first();
            if ($invitation === null || ! $invitation->isUsable() || $invitation->agency->status !== AgencyStatus::Active) {
                throw $invalid;
            }
            if (! $user->isActive() || ! $user->hasVerifiedPhone()) {
                throw new AgencyRuleException('phone_not_verified', 'Vérifiez votre numéro de téléphone avant d\'accepter.');
            }
            // Invitation par téléphone : seul le titulaire de ce numéro (vérifié) peut l'accepter.
            if ($invitation->phone !== null && $invitation->phone !== $user->phone) {
                throw $invalid;
            }
            if ($user->agencyMembership()->exists()) {
                throw new AgencyRuleException('already_in_agency', 'Vous appartenez déjà à une agence.');
            }

            $member = AgencyMember::create([
                'agency_id' => $invitation->agency_id, 'user_id' => $user->id, 'role' => MemberRole::Agent,
                'status' => MemberStatus::Active, 'can_see_all' => $invitation->can_see_all,
                'invited_at' => $invitation->created_at, 'joined_at' => now(),
            ]);
            $invitation->forceFill(['accepted_at' => now()])->save();
            $this->audit->record('agency.invitation_accepted', $invitation->agency, null, ['member' => $member->ulid], $user->id);

            return $member;
        });
    }

    public function revokeInvitation(AgencyInvitation $invitation, User $manager): void
    {
        if ($invitation->accepted_at !== null) {
            throw new AgencyRuleException('invitation_already_accepted', 'Cette invitation a déjà été acceptée.');
        }
        $invitation->forceFill(['revoked_at' => now()])->save();
        $this->audit->record('agency.invitation_revoked', $invitation->agency, null, ['invitation' => $invitation->ulid], $manager->id);
    }

    /**
     * Suspension : l'accès à l'agence est coupé dès la requête suivante (l'appartenance est relue à chaque requête),
     * ses annonces sont réattribuées au gérant, l'historique (annonceur d'origine, audit) est conservé.
     */
    public function suspend(AgencyMember $member, User $manager): AgencyMember
    {
        if ($member->isManager()) {
            throw new AgencyRuleException('cannot_suspend_manager', 'Le gérant ne peut pas être suspendu.');
        }

        return DB::transaction(function () use ($member, $manager) {
            $member->forceFill(['status' => MemberStatus::Suspended, 'suspended_at' => now()])->save();
            $reassigned = Listing::where('agency_id', $member->agency_id)->where('assigned_to', $member->user_id)
                ->update(['assigned_to' => $manager->id]);
            // Les demandes en cours restent à l'agence : elles passent aussi au gérant.
            $inquiriesReassigned = Inquiry::where('agency_id', $member->agency_id)->where('assigned_to', $member->user_id)->update(['assigned_to' => $manager->id]);
            $this->audit->record('agency.member_suspended', $member->agency, null, ['member' => $member->ulid, 'listings_reassigned' => $reassigned, 'inquiries_reassigned' => $inquiriesReassigned], $manager->id);

            return $member;
        });
    }

    public function reinstate(AgencyMember $member, User $manager): AgencyMember
    {
        $member->forceFill(['status' => MemberStatus::Active, 'suspended_at' => null])->save();
        $this->audit->record('agency.member_reinstated', $member->agency, null, ['member' => $member->ulid], $manager->id);

        return $member;
    }

    public function setSeeAll(AgencyMember $member, bool $value, User $manager): AgencyMember
    {
        $member->forceFill(['can_see_all' => $value])->save();
        $this->audit->record('agency.member_updated', $member->agency, null, ['member' => $member->ulid, 'can_see_all' => $value], $manager->id);

        return $member;
    }

    /** Attribue ou réattribue une annonce à un membre actif de la même agence (AGC-04). */
    public function assign(Listing $listing, AgencyMember $target, User $manager): Listing
    {
        if ($listing->agency_id === null || $listing->agency_id !== $target->agency_id || ! $target->isActive()) {
            throw new AgencyRuleException('invalid_assignee', 'Le destinataire doit être un membre actif de l\'agence.');
        }
        $before = $listing->assigned_to;
        $listing->forceFill(['assigned_to' => $target->user_id])->save();
        $this->audit->record('listing.assigned', $listing, ['assigned_to' => $before], ['assigned_to' => $target->user_id], $manager->id);

        return $listing;
    }
}
