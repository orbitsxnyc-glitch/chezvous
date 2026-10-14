<?php

namespace App\Services\Search;

use App\Models\Listing;
use App\Models\Locality;
use Illuminate\Contracts\Database\Query\Builder as QueryBuilder;
use Illuminate\Database\Eloquent\Builder;
use Illuminate\Support\Facades\DB;

/**
 * Requêtes publiques sur les annonces. Une annonce est visible si elle est publiée et non expirée
 * (l'expiration planifiée peut avoir du retard : on filtre aussi sur la date).
 *
 * Les critères portant sur le bien passent par une sous-requête (et non une jointure) pour que le tri
 * ne porte que sur des colonnes de `listings`, condition de la pagination par curseur.
 */
class ListingSearch
{
    public function __construct(private readonly LocalityTree $tree) {}

    /** @return Builder<Listing> */
    public function visible(): Builder
    {
        return Listing::query()
            ->where('status', 'published')
            ->where('expires_at', '>', now())
            ->whereExists(fn ($e) => $e->select(DB::raw(1))->from('properties')->whereColumn('properties.id', 'listings.property_id')->whereNull('properties.deleted_at'))
            ->where(fn ($q) => $q->whereNull('agency_id')->orWhereIn('agency_id', DB::table('agencies')->where('status', 'active')->whereNull('deleted_at')->select('id')))
            ->when(config('chezvous.search.hide_stale'), fn ($q) => $q->where('freshness_bucket', '<', 2));
    }

    /**
     * @param  array<string, mixed>  $f  filtres validés
     * @return Builder<Listing>
     */
    public function search(array $f, ?callable $extraPropertyCriteria = null): Builder
    {
        $q = $this->visible();
        $props = DB::table('properties')->select('properties.id')->whereNull('properties.deleted_at');

        $q->when($f['transaction'] ?? null, fn ($q, $v) => $q->where('transaction', $v));
        $q->when($f['agency'] ?? null, fn ($q, $slug) => $q->whereIn('agency_id', DB::table('agencies')->where('slug', $slug)->select('id')));
        $q->when($f['price_min'] ?? null, fn ($q, $v) => $q->where('price_xof', '>=', $v));
        $q->when($f['price_max'] ?? null, fn ($q, $v) => $q->where('price_xof', '<=', $v));

        $props->when($f['type'] ?? null, fn ($p, $v) => $p->whereIn('properties.property_type_id', DB::table('property_types')->where('code', $v)->select('id')));
        $props->when($f['surface_min'] ?? null, fn ($p, $v) => $p->where('properties.surface_m2', '>=', $v));
        $props->when($f['surface_max'] ?? null, fn ($p, $v) => $p->where('properties.surface_m2', '<=', $v));
        $props->when($f['bedrooms_min'] ?? null, fn ($p, $v) => $p->where('properties.bedrooms', '>=', $v));
        if (array_key_exists('furnished', $f) && $f['furnished'] !== null) {
            $props->where('properties.furnished', (bool) $f['furnished']);
        }

        if (! empty($f['locality'])) {
            $locality = Locality::where('slug', $f['locality'])->where('is_active', true)->first();
            // Localité inconnue : aucun résultat (et non « tout »).
            $props->whereIn('properties.locality_id', $locality ? $this->tree->withDescendants($locality->id) : []);
        }

        if (! empty($f['q'])) {
            $term = '%'.str_replace(['\\', '%', '_'], ['\\\\', '\\%', '\\_'], $f['q']).'%';
            $q->where(function ($w) use ($term) {
                $w->whereRaw('unaccent(listings.title) ILIKE unaccent(?)', [$term])
                    ->orWhereIn('listings.property_id', DB::table('properties as pq')->select('pq.id')
                        ->where(fn ($x) => $x->whereRaw('unaccent(pq.address) ILIKE unaccent(?)', [$term])
                            ->orWhereExists(fn ($e) => $e->select(DB::raw(1))->from('localities')
                                ->whereColumn('localities.id', 'pq.locality_id')
                                ->whereRaw('unaccent(localities.name) ILIKE unaccent(?)', [$term]))));
            });
        }

        if ($extraPropertyCriteria !== null) {
            $extraPropertyCriteria($props);
        }
        $q->whereIn('listings.property_id', $props);

        return $this->order($q, $f['sort'] ?? 'relevance');
    }

    /**
     * Tri. « relevance » : d'abord la fraîcheur (les « Remontée » viendront s'y ajouter en Phase 6), puis la date.
     *
     * @param  Builder<Listing>  $q
     * @return Builder<Listing>
     */
    private function order(Builder $q, string $sort): Builder
    {
        return match ($sort) {
            'newest' => $q->orderByDesc('published_at')->orderByDesc('id'),
            'price_asc' => $q->orderBy('price_xof')->orderBy('id'),
            'price_desc' => $q->orderByDesc('price_xof')->orderByDesc('id'),
            // Pertinence : « Remontée » actives d'abord, puis la fraîcheur, puis la date (COM, RCH-02).
            default => $q->orderByDesc('is_boosted')->orderBy('freshness_bucket')->orderByDesc('published_at')->orderByDesc('id'),
        };
    }

    /**
     * Ajoute `pub_lat` / `pub_lon` : la position PUBLIQUE du bien (jamais l'exacte).
     *
     * @param  Builder<Listing>  $q
     * @return Builder<Listing>
     */
    public function withPublicCoordinates(Builder $q): Builder
    {
        return $q->addSelect([
            'listings.*',
            DB::raw('(SELECT ST_Y(p.public_location::geometry) FROM properties p WHERE p.id = listings.property_id) AS pub_lat'),
            DB::raw('(SELECT ST_X(p.public_location::geometry) FROM properties p WHERE p.id = listings.property_id) AS pub_lon'),
        ]);
    }

    /**
     * Annonces dans une emprise (ouest, sud, est, nord), selon la position PUBLIQUE uniquement.
     *
     * @param  array<string, mixed>  $filters
     * @param  array{0: float, 1: float, 2: float, 3: float}  $bbox
     * @return Builder<Listing>
     */
    public function inBounds(array $bbox, array $filters = []): Builder
    {
        [$w, $s, $e, $n] = $bbox;
        $q = $this->search($filters, fn (QueryBuilder $props) => $props->whereNotNull('properties.public_location')
            ->whereRaw('ST_Intersects(properties.public_location, ST_MakeEnvelope(?, ?, ?, ?, 4326)::geography)', [$w, $s, $e, $n]));

        return $this->withPublicCoordinates($q);
    }

    /**
     * Annonces similaires : même transaction et type, même zone, prix à ± la tolérance configurée.
     *
     * @return Builder<Listing>
     */
    public function similar(Listing $listing): Builder
    {
        $tolerance = (float) config('chezvous.search.similar_price_tolerance');
        $property = $listing->property;
        $parent = $property->locality_id ? Locality::whereKey($property->locality_id)->value('parent_id') : null;
        $zone = $parent ? $this->tree->withDescendants((int) $parent) : ($property->locality_id ? [$property->locality_id] : []);
        $price = (int) $listing->price_xof;

        return $this->visible()
            ->where('id', '!=', $listing->id)
            ->where('transaction', $listing->transaction->value)
            ->whereBetween('price_xof', [(int) floor($price * (1 - $tolerance)), (int) ceil($price * (1 + $tolerance))])
            ->whereIn('property_id', DB::table('properties')->select('id')->whereNull('deleted_at')
                ->where('property_type_id', $property->property_type_id)->whereIn('locality_id', $zone))
            ->orderBy('freshness_bucket')->orderByDesc('published_at')
            ->limit(config('chezvous.search.similar_limit'));
    }
}
