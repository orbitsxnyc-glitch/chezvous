<?php

namespace App\Services\Agencies;

use App\Enums\ListingStatus;
use App\Models\Agency;
use App\Models\AgencyMember;
use Illuminate\Support\Facades\DB;

class AgencyStatsService
{
    /**
     * Tableau de bord (AGC-06). Les demandes, visites et taux de réponse dépendent de la Phase 5 : renvoyés à null.
     *
     * @return array<string, mixed>
     */
    public function forAgency(Agency $agency, AgencyMember $viewer): array
    {
        $perAgent = DB::table('agency_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('listings as l', function ($j) {
                $j->on('l.assigned_to', '=', 'm.user_id')->on('l.agency_id', '=', 'm.agency_id')->whereNull('l.deleted_at');
            })
            ->where('m.agency_id', $agency->id)
            ->when(! $viewer->isManager(), fn ($q) => $q->where('m.id', $viewer->id))
            ->groupBy('m.id', 'm.ulid', 'u.name', 'm.role', 'm.status')
            ->selectRaw("m.ulid as member_id, u.name, m.role, m.status,
                count(l.id) filter (where l.status = 'published') as active_listings,
                count(l.id) filter (where l.status = 'pending_moderation') as pending_listings,
                coalesce(sum(l.views_count) filter (where l.status = 'published'), 0) as views,
                coalesce(sum(l.contacts_count) filter (where l.status = 'published'), 0) as contacts")
            ->get()->map(fn ($r) => [
                'member_id' => $r->member_id, 'name' => $r->name, 'role' => $r->role, 'status' => $r->status,
                'active_listings' => (int) $r->active_listings, 'pending_listings' => (int) $r->pending_listings,
                'views' => (int) $r->views, 'contacts' => (int) $r->contacts,
                'inquiries_received' => null, 'planned_visits' => null, 'response_rate' => null,
            ])->all();

        $sum = fn (string $k) => array_sum(array_column($perAgent, $k));

        return [
            'scope' => $viewer->isManager() ? 'agency' : 'self',
            'totals' => [
                'active_listings' => $sum('active_listings'), 'pending_listings' => $sum('pending_listings'),
                'views' => $sum('views'), 'contacts' => $sum('contacts'),
                'inquiries_received' => null, 'planned_visits' => null, 'response_rate' => null,
            ],
            'quota' => $viewer->isManager() ? [
                'limit' => config('chezvous.agencies.quota_listings'),
                'used' => $agency->listings()->whereIn('status', [ListingStatus::Published->value, ListingStatus::PendingModeration->value])->count(),
            ] : null,
            'agents' => $perAgent,
            'note' => 'Demandes, visites et taux de réponse seront disponibles avec le module de demandes.',
        ];
    }
}
