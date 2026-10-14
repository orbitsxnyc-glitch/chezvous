<?php

namespace App\Services\Inquiries;

use App\Enums\InquiryStatus;
use App\Enums\VisitStatus;
use App\Models\Inquiry;
use App\Models\Visit;
use App\Services\Engagement\NotificationService;

/**
 * Rappels planifiés (DEM-04) et relance des demandes sans réponse (48 h).
 * Chaque rappel est « réservé » par une mise à jour conditionnelle : même si la tâche tourne deux fois en parallèle,
 * un rappel ne part qu'une fois.
 */
class ReminderService
{
    public function __construct(private readonly InquiryAccess $access, private readonly NotificationService $notifications) {}

    /** @return int nombre de rappels envoyés */
    public function remindVisits(): int
    {
        $sent = 0;
        [$long, $short] = config('chezvous.inquiries.visit.reminder_hours');

        Visit::with('inquiry.listing')->where('status', VisitStatus::Confirmed->value)->where('scheduled_at', '>', now())
            ->where('scheduled_at', '<=', now()->addHours($long))
            ->where(fn ($q) => $q->whereNull('reminder_24h_sent_at')->orWhereNull('reminder_2h_sent_at'))
            ->each(function (Visit $visit) use (&$sent, $long, $short) {
                $hoursLeft = now()->diffInMinutes($visit->scheduled_at, false) / 60;

                // Rappel long : seulement s'il reste assez de temps pour que ce soit utile (sinon, le rappel court suffit).
                if ($visit->reminder_24h_sent_at === null && $hoursLeft > $short) {
                    $sent += $this->claim($visit, 'reminder_24h_sent_at') ? $this->send($visit, $long) : 0;
                }
                if ($visit->reminder_2h_sent_at === null && $hoursLeft <= $short) {
                    // Rappel court atteint : le rappel long n'a plus lieu d'être.
                    Visit::whereKey($visit->id)->whereNull('reminder_24h_sent_at')->update(['reminder_24h_sent_at' => now()]);
                    $sent += $this->claim($visit, 'reminder_2h_sent_at') ? $this->send($visit, $short) : 0;
                }
            });

        return $sent;
    }

    /** @return int nombre de relances */
    public function remindUnanswered(): int
    {
        $sent = 0;
        Inquiry::with('listing')->where('status', InquiryStatus::New->value)->whereNull('first_response_at')->whereNull('unanswered_reminded_at')
            ->where('created_at', '<=', now()->subHours(config('chezvous.inquiries.unanswered_hours')))
            ->each(function (Inquiry $inquiry) use (&$sent) {
                $claimed = Inquiry::whereKey($inquiry->id)->whereNull('unanswered_reminded_at')->update(['unanswered_reminded_at' => now()]);
                if ($claimed === 1) {
                    $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), 'inquiry.unanswered', ['inquiry' => $inquiry->ulid, 'listing' => $inquiry->listing->reference]);
                    $sent++;
                }
            });

        return $sent;
    }

    private function claim(Visit $visit, string $column): bool
    {
        return Visit::whereKey($visit->id)->whereNull($column)->update([$column => now()]) === 1;
    }

    private function send(Visit $visit, int $hours): int
    {
        $inquiry = $visit->inquiry;
        $data = ['inquiry' => $inquiry->ulid, 'visit' => $visit->ulid, 'listing' => $inquiry->listing->reference, 'hours_before' => $hours, 'scheduled_at' => $visit->scheduled_at?->toIso8601String()];
        $this->notifications->notify($inquiry->requester_id, 'visit.reminder', $data);
        $this->notifications->notifyMany($this->access->ownerSideRecipients($inquiry), 'visit.reminder', $data);

        return 1;
    }
}
