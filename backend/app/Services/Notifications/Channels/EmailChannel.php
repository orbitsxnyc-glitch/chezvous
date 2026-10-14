<?php

namespace App\Services\Notifications\Channels;

use App\Models\User;
use Illuminate\Support\Facades\Mail;

/** E-mail : seul canal réel de la Phase 7. Avec un pilote « log » ou « array », rien n'est remis : résultat « bouchon ». */
class EmailChannel implements NotificationChannel
{
    public function send(User $user, string $type, ?string $subject, string $body): DeliveryResult
    {
        if ($user->email === null) {
            return DeliveryResult::skipped('no_email');
        }

        Mail::raw($body, fn ($m) => $m->to($user->email)->subject($subject ?? 'ChezVous'));

        $mailer = (string) config('mail.default');

        return in_array($mailer, ['log', 'array'], true) ? DeliveryResult::stubbed($mailer) : DeliveryResult::sent($mailer);
    }
}
