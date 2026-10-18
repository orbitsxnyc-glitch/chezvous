<?php

namespace App\Services\Stays;

use App\Enums\UnitStatus;
use App\Models\Locality;
use App\Models\Unit;
use App\Services\Search\LocalityTree;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Recherche publique d'unités réservables. La disponibilité affichée est informative : elle ne bloque rien
 * tant qu'une demande de séjour n'a pas été acceptée.
 */
class StaySearch
{
    public function __construct(private readonly LocalityTree $tree) {}

    /** @return Builder<Unit> */
    public function visible(): Builder
    {
        return Unit::query()
            ->where('status', UnitStatus::Active->value)
            ->whereNotNull('approved_at')
            ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('properties')->whereColumn('properties.id', 'units.property_id')->whereNull('properties.deleted_at'))
            ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('rate_plans')->whereColumn('rate_plans.unit_id', 'units.id'))
            ->where(fn ($q) => $q->whereNull('agency_id')->orWhereIn('agency_id', DB::table('agencies')->where('status', 'active')->whereNull('deleted_at')->select('id')));
    }

    /**
     * @param  array<string, mixed>  $f  filtres validés : check_in, check_out, guests, locality, price_max
     * @return Builder<Unit>
     */
    public function search(array $f): Builder
    {
        $q = $this->visible();

        $q->when($f['guests'] ?? null, fn ($q, $v) => $q->where('capacity_guests', '>=', $v));
        $q->when($f['price_max'] ?? null, fn ($q, $v) => $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('rate_plans')->whereColumn('rate_plans.unit_id', 'units.id')->where('rate_plans.base_nightly_xof', '<=', $v)));

        if (! empty($f['locality'])) {
            $locality = Locality::where('slug', $f['locality'])->where('is_active', true)->first();
            $ids = $locality ? $this->tree->withDescendants($locality->id) : [];
            $q->whereIn('property_id', DB::table('properties')->whereIn('locality_id', $ids)->select('id'));
        }

        if (! empty($f['check_in']) && ! empty($f['check_out'])) {
            $dates = StayDates::make($f['check_in'], $f['check_out']);
            $q->whereNotExists(fn ($e) => $e->select(DB::raw(1))->from('calendar_blocks')
                ->whereColumn('calendar_blocks.unit_id', 'units.id')
                ->where('calendar_blocks.starts_on', '<', $dates->checkOut->toDateString())
                ->where('calendar_blocks.ends_on', '>', $dates->checkIn->toDateString()));
            // Durées autorisées par le tarif de base (les exceptions de durée sont revalidées au devis).
            $nights = $dates->nights();
            $q->whereExists(fn ($e) => $e->select(DB::raw(1))->from('rate_plans')->whereColumn('rate_plans.unit_id', 'units.id')
                ->where('rate_plans.min_nights', '<=', $nights)
                ->where(fn ($x) => $x->whereNull('rate_plans.max_nights')->orWhere('rate_plans.max_nights', '>=', $nights)));
        }

        return $q;
    }
}
