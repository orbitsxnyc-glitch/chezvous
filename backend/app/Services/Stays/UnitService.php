<?php

namespace App\Services\Stays;

use App\Enums\UnitStatus;
use App\Exceptions\StayRuleException;
use App\Models\Property;
use App\Models\Unit;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Facades\DB;

/**
 * Création, tarification et changements d'état des unités. Une unité ne devient active qu'avec un tarif.
 */
class UnitService
{
    /** Transitions autorisées : tout passe par ce tableau, jamais par une modification libre du statut. */
    private const TRANSITIONS = [
        'draft' => ['active', 'archived'],
        'active' => ['paused', 'archived'],
        'paused' => ['active', 'archived'],
        'archived' => [],
    ];

    public function __construct(private readonly AuditLogger $audit) {}

    /**
     * @param  array{name: string, capacity_guests: int, beds?: int|null}  $data
     */
    public function create(User $owner, Property $property, array $data, ?int $agencyId = null): Unit
    {
        return DB::transaction(function () use ($owner, $property, $data, $agencyId) {
            $unit = Unit::create([
                'property_id' => $property->id,
                'owner_user_id' => $owner->id,
                'agency_id' => $agencyId,
                'name' => $data['name'],
                'capacity_guests' => $data['capacity_guests'],
                'beds' => $data['beds'] ?? null,
            ]);
            $this->audit->record('unit.created', $unit, null, ['property' => $property->ulid], $owner->id);

            return $unit;
        });
    }

    /**
     * @param  array{name?: string, capacity_guests?: int, beds?: int|null}  $data
     */
    public function update(Unit $unit, User $actor, array $data): Unit
    {
        $before = $unit->only(['name', 'capacity_guests', 'beds']);
        $unit->fill(array_intersect_key($data, array_flip(['name', 'capacity_guests', 'beds'])))->save();
        $this->audit->record('unit.updated', $unit, $before, $unit->only(['name', 'capacity_guests', 'beds']), $actor->id);

        return $unit;
    }

    /**
     * @param  array{base_nightly_xof: int, min_nights?: int, max_nights?: int|null, cleaning_fee_xof?: int}  $data
     */
    public function setRatePlan(Unit $unit, User $actor, array $data): Unit
    {
        $plan = $unit->ratePlan()->updateOrCreate([], [
            'base_nightly_xof' => $data['base_nightly_xof'],
            'min_nights' => $data['min_nights'] ?? 1,
            'max_nights' => $data['max_nights'] ?? null,
            'cleaning_fee_xof' => $data['cleaning_fee_xof'] ?? 0,
        ]);
        $this->audit->record('unit.rate_plan_set', $unit, null, $plan->only(['base_nightly_xof', 'min_nights', 'max_nights', 'cleaning_fee_xof']), $actor->id);

        return $unit->load('ratePlan');
    }

    public function addOverride(Unit $unit, User $actor, string $startsOn, string $endsOn, int $nightlyXof, ?int $minNights = null, int $priority = 0): void
    {
        $dates = StayDates::make($startsOn, $endsOn);
        $unit->rateOverrides()->create([
            'starts_on' => $dates->checkIn->toDateString(),
            'ends_on' => $dates->checkOut->toDateString(),
            'nightly_xof' => $nightlyXof,
            'min_nights' => $minNights,
            'priority' => $priority,
        ]);
        $this->audit->record('unit.rate_override_added', $unit, null, ['from' => $startsOn, 'to' => $endsOn, 'nightly_xof' => $nightlyXof, 'priority' => $priority], $actor->id);
    }

    /**
     * Validation par la modération : l'unité doit être active, tarifée, localisée et illustrée.
     * Retirer l'approbation (ou mettre en pause) la rend invisible au public.
     */
    public function approve(Unit $unit, User $moderator): Unit
    {
        $missing = [];
        if ($unit->status !== UnitStatus::Active) {
            $missing[] = 'unité non active';
        }
        if ($unit->ratePlan === null) {
            $missing[] = 'tarif';
        }
        $property = $unit->property;
        if ($property->locality_id === null) {
            $missing[] = 'quartier';
        }
        if (! $property->images()->where('status', 'ready')->exists()) {
            $missing[] = 'photo';
        }
        if ($missing !== []) {
            throw new StayRuleException('incomplete', 'Fiche incomplète : '.implode(', ', $missing).'.', ['missing' => $missing]);
        }

        $unit->forceFill(['approved_at' => now(), 'approved_by' => $moderator->id])->save();
        $this->audit->record('unit.approved', $unit, null, null, $moderator->id);

        return $unit;
    }

    public function revokeApproval(Unit $unit, User $moderator, string $reason): Unit
    {
        $unit->forceFill(['approved_at' => null, 'approved_by' => null])->save();
        $this->audit->record('unit.approval_revoked', $unit, null, ['reason' => $reason], $moderator->id);

        return $unit;
    }

    public function transition(Unit $unit, UnitStatus $to, User $actor): Unit
    {
        $from = $unit->status;
        if (! in_array($to->value, self::TRANSITIONS[$from->value], true)) {
            throw new StayRuleException('invalid_transition', "Une unité « {$from->value} » ne peut pas passer à « {$to->value} ».");
        }
        if ($to === UnitStatus::Active && $unit->ratePlan === null) {
            throw new StayRuleException('no_rate_plan', 'Définissez un tarif avant d\'activer cette unité.');
        }

        $unit->status = $to;
        $unit->save();
        $this->audit->record('unit.status_changed', $unit, ['status' => $from->value], ['status' => $to->value], $actor->id);

        return $unit;
    }
}
