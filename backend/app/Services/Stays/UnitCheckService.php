<?php

namespace App\Services\Stays;

use App\Enums\CheckStatus;
use App\Enums\CheckType;
use App\Exceptions\StayRuleException;
use App\Models\Unit;
use App\Models\User;
use App\Models\VerificationCheck;
use App\Services\Audit\AuditLogger;
use App\Services\Commercial\SettingsService;
use Carbon\CarbonInterface;
use Illuminate\Support\Collection;

/**
 * Contrôles d'un logement de séjour (identité de l'hôte, localisation, document, visite, agrément touristique).
 * Un contrôle est un enregistrement daté, jamais modifié : le dernier de chaque type fait foi, et il peut expirer.
 * Aucun « badge global » : seuls les contrôles réellement effectués sont affichés, avec leur date. Un contrôle ne garantit pas
 * la propriété juridique du bien.
 */
class UnitCheckService
{
    /** @var list<CheckType> */
    public const TYPES = [CheckType::Identity, CheckType::Location, CheckType::Document, CheckType::FieldVisit, CheckType::TourismLicense];

    public function __construct(private readonly AuditLogger $audit, private readonly SettingsService $settings) {}

    public function record(Unit $unit, CheckType $type, CheckStatus $status, User $admin, ?string $note, ?CarbonInterface $validUntil): VerificationCheck
    {
        if (! in_array($type, self::TYPES, true)) {
            throw new StayRuleException('check_not_manual', 'Ce contrôle ne se saisit pas pour un logement.');
        }
        if (! in_array($status, [CheckStatus::Passed, CheckStatus::Failed], true)) {
            throw new StayRuleException('invalid_check_status', 'Un contrôle saisi est soit réussi, soit échoué.');
        }
        if ($status === CheckStatus::Failed && blank($note)) {
            throw new StayRuleException('note_required', 'Précisez pourquoi le contrôle a échoué : la note est conservée.');
        }

        [$subjectType, $subjectId] = $type === CheckType::Identity ? ['user', $unit->owner_user_id] : ['property', $unit->property_id];
        $check = VerificationCheck::create([
            'subject_type' => $subjectType, 'subject_id' => $subjectId, 'check_type' => $type, 'status' => $status,
            'performed_by' => $admin->id, 'performed_at' => now(), 'valid_until' => $validUntil, 'note' => $note,
        ]);
        $this->audit->record('unit.check_recorded', $unit, null, ['check' => $type->value, 'status' => $status->value, 'valid_until' => $validUntil?->toIso8601String()], $admin->id);

        return $check;
    }

    /** @return list<string> types exigés avant la validation d'une unité (réglage en base, sinon configuration) */
    public function requiredTypes(): array
    {
        $s = $this->settings->get('stay_required_checks');
        $types = $s['types'] ?? config('chezvous.stays.required_checks');

        return array_values(array_filter((array) $types, fn ($t) => CheckType::tryFrom((string) $t) !== null && in_array(CheckType::from((string) $t), self::TYPES, true)));
    }

    /**
     * Dernier contrôle de chaque type.
     *
     * @return Collection<string, VerificationCheck>
     */
    public function latest(Unit $unit): Collection
    {
        return VerificationCheck::query()
            ->where(fn ($q) => $q->where(fn ($w) => $w->where('subject_type', 'property')->where('subject_id', $unit->property_id))
                ->orWhere(fn ($w) => $w->where('subject_type', 'user')->where('subject_id', $unit->owner_user_id)))
            ->whereIn('check_type', array_map(fn (CheckType $t) => $t->value, self::TYPES))
            ->orderByDesc('performed_at')->orderByDesc('id')->get()
            ->unique(fn (VerificationCheck $c) => $c->check_type->value)
            ->keyBy(fn (VerificationCheck $c) => $c->check_type->value);
    }

    /** Un contrôle compte s'il a réussi et n'a pas expiré. */
    public function isValid(?VerificationCheck $check): bool
    {
        return $check !== null && $check->status === CheckStatus::Passed && ($check->valid_until === null || $check->valid_until->isFuture());
    }

    /** @return list<string> types exigés mais absents, échoués ou expirés */
    public function missingRequired(Unit $unit): array
    {
        $latest = $this->latest($unit);

        return array_values(array_filter($this->requiredTypes(), fn (string $t) => ! $this->isValid($latest->get($t))));
    }

    /**
     * Contrôles réussis et valides, pour l'affichage public (type, date, validité). Jamais de note ni de justificatif.
     *
     * @return list<array{type: string, performed_at: string, valid_until: string|null}>
     */
    public function publicChecks(Unit $unit): array
    {
        return $this->latest($unit)->filter(fn ($c) => $this->isValid($c))->map(fn (VerificationCheck $c) => [
            'type' => $c->check_type->value, 'performed_at' => $c->performed_at->toIso8601String(), 'valid_until' => $c->valid_until?->toIso8601String(),
        ])->values()->all();
    }
}
