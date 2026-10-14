<?php

namespace App\Http\Controllers\Api\V1\Admin;

use App\Http\Controllers\Controller;
use Illuminate\Http\JsonResponse;
use Illuminate\Support\Facades\DB;
use Illuminate\Support\Facades\Gate;

/** Tableau de bord (ADM-06). Distingue toujours le « confirmé » du « déclaré » : un règlement déclaré n'est pas un revenu. */
class DashboardController extends Controller
{
    public function __invoke(): JsonResponse
    {
        Gate::authorize('moderate');
        $monthStart = now()->startOfMonth();
        $count = fn (string $table, ?callable $where = null) => (int) tap(DB::table($table), fn ($q) => $where && $where($q))->count();
        $daily = fn (string $table, string $column, ?callable $where = null) => DB::table($table)
            ->selectRaw("to_char(date_trunc('day', {$column}), 'YYYY-MM-DD') as day, count(*) as n")->where($column, '>=', now()->subDays(29)->startOfDay())
            ->when($where, fn ($q) => $where($q))->groupBy('day')->orderBy('day')->pluck('n', 'day');

        return response()->json(['data' => [
            'registrations' => [
                'today' => $count('users', fn ($q) => $q->where('created_at', '>=', now()->startOfDay())),
                'last_7_days' => $count('users', fn ($q) => $q->where('created_at', '>=', now()->subDays(7))),
                'last_30_days' => $count('users', fn ($q) => $q->where('created_at', '>=', now()->subDays(30))),
                'total' => $count('users', fn ($q) => $q->whereNull('deleted_at')),
            ],
            'listings' => [
                'published' => $count('listings', fn ($q) => $q->where('status', 'published')->where('expires_at', '>', now())->whereNull('deleted_at')),
                'pending_moderation' => $count('listings', fn ($q) => $q->where('status', 'pending_moderation')),
                'published_last_30_days' => $count('listings', fn ($q) => $q->where('published_at', '>=', now()->subDays(30))),
            ],
            'moderation' => [
                'open_reports' => $count('listing_reports', fn ($q) => $q->where('status', 'open')),
                'pending_agency_verifications' => $count('verification_requests', fn ($q) => $q->where('status', 'pending')),
            ],
            'inquiries' => [
                'sent_last_30_days' => $count('inquiries', fn ($q) => $q->where('created_at', '>=', now()->subDays(30))),
                'open' => $count('inquiries', fn ($q) => $q->whereNotIn('status', ['concluded', 'lost', 'closed'])),
            ],
            'commercial' => [
                'active_subscriptions' => $count('subscriptions', fn ($q) => $q->where('status', 'active')),
                'active_premium' => $count('premium_listings', fn ($q) => $q->where('status', 'active')),
                'pending_requests' => $count('subscriptions', fn ($q) => $q->where('status', 'pending')) + $count('premium_listings', fn ($q) => $q->where('status', 'pending')),
                // Revenu CONFIRMÉ du mois (encaissements confirmés) et, à part, les montants déclarés non encore confirmés.
                'confirmed_revenue_month_xof' => (int) DB::table('offline_payments')->where('status', 'confirmed')->where('received_at', '>=', $monthStart)->sum('amount_xof'),
                'declared_unconfirmed_xof' => (int) DB::table('offline_payments')->where('status', 'declared')->sum('amount_xof'),
                'declared_unconfirmed_count' => $count('offline_payments', fn ($q) => $q->where('status', 'declared')),
            ],
            'notifications' => [
                'failed_last_24h' => $count('notification_deliveries', fn ($q) => $q->where('status', 'failed')->where('created_at', '>=', now()->subDay())),
            ],
            'series_30_days' => [
                'registrations' => $daily('users', 'created_at'),
                'listings_published' => $daily('listings', 'published_at', fn ($q) => $q->whereNotNull('published_at')),
                'inquiries' => $daily('inquiries', 'created_at'),
            ],
        ]]);
    }
}
