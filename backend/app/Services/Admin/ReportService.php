<?php

namespace App\Services\Admin;

use App\Enums\ReportStatus;
use App\Exceptions\AdminRuleException;
use App\Models\ListingReport;
use App\Models\User;
use App\Services\Audit\AuditLogger;
use App\Services\Engagement\NotificationService;
use App\Services\Listings\ListingStateService;
use Illuminate\Support\Facades\DB;

/**
 * Traitement des signalements (ADM-02) : masquer l'annonce, avertir ou suspendre l'annonceur, ou classer sans suite.
 * Chaque décision est motivée, auditée, et clôt tous les signalements ouverts sur la même annonce.
 */
class ReportService
{
    public function __construct(
        private readonly ListingStateService $states,
        private readonly UserAdminService $users,
        private readonly NotificationService $notifications,
        private readonly AuditLogger $audit,
    ) {}

    public function hide(ListingReport $report, User $admin, string $note): ListingReport
    {
        return $this->handle($report, $admin, 'hide', $note, function () use ($report, $admin) {
            $this->states->hide($report->listing, $admin, $report->reason_code);
        });
    }

    public function warn(ListingReport $report, User $admin, string $message): ListingReport
    {
        return $this->handle($report, $admin, 'warn', $message, function () use ($report, $message) {
            $listing = $report->listing;
            $this->notifications->notifyMany(array_filter([$listing->advertiser_user_id, $listing->assigned_to]), 'moderation.warning', ['listing' => $listing->reference, 'reason' => $message]);
        });
    }

    public function suspendAdvertiser(ListingReport $report, User $admin, string $reason): ListingReport
    {
        return $this->handle($report, $admin, 'suspend', $reason, function () use ($report, $admin, $reason) {
            $this->users->suspend($report->listing->advertiser, $admin, $reason);
        });
    }

    public function dismiss(ListingReport $report, User $admin, string $note): ListingReport
    {
        return $this->handle($report, $admin, 'none', $note, null, ReportStatus::Dismissed);
    }

    private function handle(ListingReport $report, User $admin, string $action, string $note, ?callable $effect, ReportStatus $final = ReportStatus::Actioned): ListingReport
    {
        return DB::transaction(function () use ($report, $admin, $action, $note, $effect, $final) {
            $locked = ListingReport::whereKey($report->id)->lockForUpdate()->firstOrFail();
            if ($locked->status !== ReportStatus::Open) {
                throw new AdminRuleException('invalid_transition', 'Ce signalement a déjà été traité.');
            }
            if ($effect !== null) {
                $effect();
            }
            // Tous les signalements ouverts sur la même annonce reçoivent la même décision.
            ListingReport::where('listing_id', $locked->listing_id)->where('status', ReportStatus::Open->value)
                ->update(['status' => $final->value, 'action' => $action, 'handled_by' => $admin->id, 'handled_at' => now(), 'handler_note' => $note]);
            $this->audit->record('report.handled', $locked->listing, null, ['action' => $action, 'note' => $note], $admin->id);

            return $locked->refresh();
        });
    }
}
