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
        $inquiryStats = $this->inquiryStats($agency);

        $perAgent = DB::table('agency_members as m')
            ->join('users as u', 'u.id', '=', 'm.user_id')
            ->leftJoin('listings as l', function ($j) {
                $j->on('l.assigned_to', '=', 'm.user_id')->on('l.agency_id', '=', 'm.agency_id')->whereNull('l.deleted_at');
            })
            ->where('m.agency_id', $agency->id)
            ->when(! $viewer->isManager(), fn ($q) => $q->where('m.id', $viewer->id))
            ->groupBy('m.id', 'm.ulid', 'm.user_id', 'u.name', 'm.role', 'm.status')
            ->selectRaw("m.ulid as member_id, m.user_id, u.name, m.role, m.status,
                count(l.id) filter (where l.status = 'published') as active_listings,
                count(l.id) filter (where l.status = 'pending_moderation') as pending_listings,
                coalesce(sum(l.views_count) filter (where l.status = 'published'), 0) as views,
                coalesce(sum(l.contacts_count) filter (where l.status = 'published'), 0) as contacts")
            ->get()->map(fn ($r) => [
                'member_id' => $r->member_id, 'name' => $r->name, 'role' => $r->role, 'status' => $r->status,
                'active_listings' => (int) $r->active_listings, 'pending_listings' => (int) $r->pending_listings,
                'views' => (int) $r->views, 'contacts' => (int) $r->contacts,
            ] + ($inquiryStats[$r->user_id] ?? ['inquiries_received' => 0, 'planned_visits' => 0, 'response_rate' => null]))->all();

        $sum = fn (string $k) => array_sum(array_column($perAgent, $k));

        return [
            'scope' => $viewer->isManager() ? 'agency' : 'self',
            'totals' => [
                'active_listings' => $sum('active_listings'), 'pending_listings' => $sum('pending_listings'),
                'views' => $sum('views'), 'contacts' => $sum('contacts'),
                'inquiries_received' => $sum('inquiries_received'), 'planned_visits' => $sum('planned_visits'),
                'response_rate' => $this->agencyRate($agency, $viewer),
            ],
            'quota' => $viewer->isManager() ? [
                'limit' => config('chezvous.agencies.quota_listings'),
                'used' => $agency->listings()->whereIn('status', [ListingStatus::Published->value, ListingStatus::PendingModeration->value])->count(),
            ] : null,
            'agents' => $perAgent,
            'note' => 'Demandes reçues et taux de réponse calculés sur '.config('chezvous.inquiries.response_window_days').' jours ; une demande est « répondue » si une réponse est donnée sous '.config('chezvous.inquiries.unanswered_hours').' h.',
        ];
    }

    /**
     * Demandes reçues, visites confirmées à venir et taux de réponse par agent attribué.
     *
     * @return array<int, array{inquiries_received: int, planned_visits: int, response_rate: float|null}>
     */
    private function inquiryStats(Agency $agency): array
    {
        $window = now()->subDays(config('chezvous.inquiries.response_window_days'));
        $limitHours = (int) config('chezvous.inquiries.unanswered_hours');
        $cutoff = now()->subHours($limitHours); // horloge de l'application (et non celle de la base)
        $rows = DB::table('inquiries')->where('agency_id', $agency->id)->whereNotNull('assigned_to')->where('created_at', '>=', $window)->groupBy('assigned_to')
            ->selectRaw('assigned_to, count(*) as received,
                count(*) filter (where first_response_at is not null and first_response_at <= created_at + make_interval(hours => ?)) as answered_in_time,
                count(*) filter (where first_response_at is not null or created_at <= ?) as eligible', [$limitHours, $cutoff])->get();

        $visits = DB::table('visits')->join('inquiries', 'inquiries.id', '=', 'visits.inquiry_id')->where('inquiries.agency_id', $agency->id)
            ->where('visits.status', 'confirmed')->where('visits.scheduled_at', '>', now())->groupBy('inquiries.assigned_to')
            ->selectRaw('inquiries.assigned_to, count(*) as planned')->pluck('planned', 'assigned_to');

        $out = [];
        foreach ($rows as $r) {
            $out[$r->assigned_to] = [
                'inquiries_received' => (int) $r->received,
                'planned_visits' => (int) ($visits[$r->assigned_to] ?? 0),
                // Pas de taux tant qu'aucune demande n'est « échue » ou répondue : null, jamais un faux zéro.
                'response_rate' => $r->eligible > 0 ? round($r->answered_in_time / $r->eligible, 3) : null,
            ];
        }
        foreach ($visits as $assignee => $planned) {
            $out[$assignee] ??= ['inquiries_received' => 0, 'planned_visits' => 0, 'response_rate' => null];
            $out[$assignee]['planned_visits'] = (int) $planned;
        }

        return $out;
    }

    /** Taux de réponse de l'agence (gérant) ou de l'agent lui-même. */
    private function agencyRate(Agency $agency, AgencyMember $viewer): ?float
    {
        $window = now()->subDays(config('chezvous.inquiries.response_window_days'));
        $limitHours = (int) config('chezvous.inquiries.unanswered_hours');
        $cutoff = now()->subHours($limitHours);
        $r = DB::table('inquiries')->where('agency_id', $agency->id)->where('created_at', '>=', $window)
            ->when(! $viewer->isManager(), fn ($q) => $q->where('assigned_to', $viewer->user_id))
            ->selectRaw('count(*) filter (where first_response_at is not null and first_response_at <= created_at + make_interval(hours => ?)) as ok,
                count(*) filter (where first_response_at is not null or created_at <= ?) as eligible', [$limitHours, $cutoff])->first();

        return $r->eligible > 0 ? round($r->ok / $r->eligible, 3) : null;
    }
}
