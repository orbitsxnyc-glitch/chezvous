<?php

namespace App\Services\Admin;

use App\Enums\ListingStatus;
use App\Enums\UserStatus;
use App\Exceptions\AdminRuleException;
use App\Models\Listing;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\DB;

/**
 * Suspension et réactivation de comptes (ADM-03). Une suspension coupe les accès tout de suite, révoque les jetons
 * et masque les annonces publiques personnelles ; rien n'est supprimé.
 */
class UserAdminService
{
    public function __construct(
        private readonly ListingStateService $states,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    /** Un administrateur ne suspend pas un autre administrateur : seul un super administrateur le peut, jamais lui-même. */
    public function assertCanModerate(User $actor, User $target): void
    {
        if ($actor->id === $target->id) {
            throw new AdminRuleException('cannot_target_self', 'Vous ne pouvez pas agir sur votre propre compte.', 403);
        }
        if ($target->hasRole('super_admin')) {
            throw new AdminRuleException('forbidden_target', 'Un super administrateur ne peut pas être suspendu.', 403);
        }
        if ($target->hasRole('admin') && ! $actor->hasRole('super_admin')) {
            throw new AdminRuleException('forbidden_target', 'Seul un super administrateur peut agir sur un administrateur.', 403);
        }
    }

    public function suspend(User $target, User $actor, string $reason): User
    {
        $this->assertCanModerate($actor, $target);
        if ($target->status === UserStatus::Deleted) {
            throw new AdminRuleException('invalid_transition', 'Ce compte est supprimé.');
        }
        if ($target->status === UserStatus::Suspended) {
            throw new AdminRuleException('invalid_transition', 'Ce compte est déjà suspendu.');
        }

        return DB::transaction(function () use ($target, $actor, $reason) {
            $target->forceFill(['status' => UserStatus::Suspended])->save();
            $target->tokens()->delete(); // jetons mobiles : accès coupé immédiatement
            DB::table('sessions')->where('user_id', $target->id)->delete();

            $hidden = 0;
            Listing::where('advertiser_user_id', $target->id)->whereNull('agency_id')->where('status', ListingStatus::Published->value)
                ->each(function (Listing $l) use ($actor, &$hidden) {
                    $this->states->hide($l, $actor, 'account_suspended');
                    $hidden++;
                });
            $this->audit->record('user.suspended', $target, ['status' => 'active'], ['status' => 'suspended', 'reason' => $reason, 'listings_hidden' => $hidden], $actor->id);
            $this->notifications->notify($target, 'security.account_suspended');

            return $target;
        });
    }

    /** Réactivation : l'accès revient ; les annonces masquées restent masquées (l'utilisateur peut en publier de nouvelles). */
    public function reactivate(User $target, User $actor): User
    {
        $this->assertCanModerate($actor, $target);
        if ($target->status !== UserStatus::Suspended) {
            throw new AdminRuleException('invalid_transition', 'Ce compte n\'est pas suspendu.');
        }
        $target->forceFill(['status' => UserStatus::Active])->save();
        $this->audit->record('user.reactivated', $target, ['status' => 'suspended'], ['status' => 'active'], $actor->id);

        return $target;
    }

    /** Attribution ou retrait du rôle administrateur : super administrateur uniquement (vérifié par la Gate). */
    public function setAdminRole(User $target, User $actor, bool $admin): User
    {
        if ($actor->id === $target->id) {
            throw new AdminRuleException('cannot_target_self', 'Vous ne pouvez pas modifier votre propre rôle.', 403);
        }
        if ($target->hasRole('super_admin')) {
            throw new AdminRuleException('forbidden_target', 'Le rôle d\'un super administrateur ne se modifie pas ici.', 403);
        }
        if ($admin && $target->status !== UserStatus::Active) {
            throw new AdminRuleException('invalid_target', 'Le compte doit être actif.');
        }
        $had = $target->hasRole('admin');
        $admin ? $target->assignRole('admin') : $target->removeRole('admin');
        if ($had !== $admin) {
            $this->audit->record($admin ? 'user.admin_granted' : 'user.admin_revoked', $target, null, null, $actor->id);
        }

        return $target;
    }
}
