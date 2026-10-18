<?php

namespace App\Services\Stays;

use App\Enums\CalendarBlockKind;
use App\Exceptions\StayRuleException;
use App\Models\CalendarBlock;
use App\Models\Unit;
use App\Models\UnitOccupancy;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use Illuminate\Support\Collection;

/**
 * Fermetures du calendrier d'une unité. La disponibilité d'une recherche est informative : elle ne bloque rien
 * tant qu'un séjour n'est pas accepté (lots suivants).
 */
class CalendarService
{
    public function __construct(private readonly AuditLogger $audit) {}

    public function block(Unit $unit, User $actor, string $startsOn, string $endsOn, CalendarBlockKind $kind = CalendarBlockKind::Manual, ?string $note = null): CalendarBlock
    {
        $dates = StayDates::make($startsOn, $endsOn);
        $max = (int) config('chezvous.stays.max_block_days');
        if ($dates->nights() > $max) {
            throw new StayRuleException('block_too_long', "Une fermeture ne peut pas dépasser {$max} jours.");
        }

        $block = $unit->calendarBlocks()->create([
            'starts_on' => $dates->checkIn->toDateString(),
            'ends_on' => $dates->checkOut->toDateString(),
            'kind' => $kind,
            'note' => $note,
            'created_by' => $actor->id,
        ]);
        $this->audit->record('unit.calendar_blocked', $unit, null, ['from' => $block->starts_on->toDateString(), 'to' => $block->ends_on->toDateString(), 'kind' => $kind->value], $actor->id);

        return $block;
    }

    public function unblock(CalendarBlock $block, User $actor): void
    {
        $unit = Unit::findOrFail($block->unit_id);
        $before = ['from' => $block->starts_on->toDateString(), 'to' => $block->ends_on->toDateString()];
        $block->delete();
        $this->audit->record('unit.calendar_unblocked', $unit, $before, null, $actor->id);
    }

    /**
     * Fermetures qui touchent l'intervalle [arrivée, départ).
     *
     * @return Collection<int, CalendarBlock>
     */
    public function conflicts(Unit $unit, StayDates $dates): Collection
    {
        return $unit->calendarBlocks()
            ->where('starts_on', '<', $dates->checkOut->toDateString())
            ->where('ends_on', '>', $dates->checkIn->toDateString())
            ->get();
    }

    /**
     * Séjours qui occupent déjà les dates : verrous non expirés et séjours confirmés.
     * Un verrou expiré mais pas encore nettoyé par la tâche planifiée ne bloque pas (contrôle sur `expires_at`).
     *
     * @return Collection<int, UnitOccupancy>
     */
    public function occupancyConflicts(Unit $unit, StayDates $dates): Collection
    {
        return UnitOccupancy::where('unit_id', $unit->id)
            ->whereIn('status', ['held', 'confirmed', 'checked_in'])
            ->where(fn ($q) => $q->whereNull('expires_at')->orWhere('expires_at', '>', now()))
            ->where('check_in', '<', $dates->checkOut->toDateString())
            ->where('check_out', '>', $dates->checkIn->toDateString())
            ->get();
    }

    public function isAvailable(Unit $unit, StayDates $dates): bool
    {
        return $this->conflicts($unit, $dates)->isEmpty() && $this->occupancyConflicts($unit, $dates)->isEmpty();
    }
}
