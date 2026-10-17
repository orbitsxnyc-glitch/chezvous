<?php

namespace App\Services\Passport;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Exceptions\AdminRuleException;
use App\Models\Listing;
use App\Models\User;
use App\Models\VerificationCheck;
use App\Services\Audit\AuditLogger;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Saisie par un administrateur des contrôles du Passeport du Bien (identité, localisation, document, visite terrain).
 * Un contrôle est un enregistrement daté et jamais modifié : rejouer un contrôle en ajoute un nouveau, le dernier fait foi.
 * Le téléphone vient de l'inscription, le contrôle professionnel de la vérification d'agence : ils ne se saisissent pas ici.
 */
class PassportCheckService
{
    /** @var list<CheckType> */
    public const MANUAL_TYPES = [CheckType::Identity, CheckType::Location, CheckType::Document, CheckType::FieldVisit];

    public function __construct(private readonly AuditLogger $audit) {}

    public function record(Listing $listing, CheckType $type, CheckStatus $status, User $admin, ?string $note, ?CarbonInterface $validUntil): VerificationCheck
    {
        if (! in_array($type, self::MANUAL_TYPES, true)) {
            throw new AdminRuleException('check_not_manual', 'Ce contrôle ne se saisit pas à la main : il découle de l\'inscription ou de la vérification d\'agence.');
        }
        if (! in_array($status, [CheckStatus::Passed, CheckStatus::Failed], true)) {
            throw new AdminRuleException('invalid_check_status', 'Un contrôle saisi est soit réussi, soit échoué.');
        }
        if ($status === CheckStatus::Failed && blank($note)) {
            throw new AdminRuleException('note_required', 'Précisez pourquoi le contrôle a échoué : la note est conservée dans l\'historique.');
        }

        // L'identité concerne l'annonceur ; la localisation, le document et la visite concernent le bien.
        [$subjectType, $subjectId] = $type === CheckType::Identity
            ? ['user', $listing->advertiser_user_id]
            : ['property', $listing->property_id];

        $check = VerificationCheck::create([
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'check_type' => $type, 'status' => $status,
            'performed_by' => $admin->id, 'performed_at' => now(), 'valid_until' => $validUntil, 'note' => $note,
        ]);
        $this->audit->record('passport.check_recorded', $listing, null, [
            'check' => $type->value, 'status' => $status->value, 'subject' => $subjectType, 'valid_until' => $validUntil?->toIso8601String(),
        ], $admin->id);

        return $check;
    }

    /**
     * Historique complet (administrateur), du plus récent au plus ancien.
     *
     * @return Collection<int, VerificationCheck>
     */
    public function history(Listing $listing): Collection
    {
        return VerificationCheck::query()
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('subject_type', 'property')->where('subject_id', $listing->property_id))
                ->orWhere(fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $listing->advertiser_user_id)))
            ->orderByDesc('performed_at')->orderByDesc('id')->get();
    }
}
