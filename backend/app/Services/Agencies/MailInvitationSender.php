<?php

namespace App\Services\Agencies;

use App\Contracts\InvitationSender;
use App\Mail\AgencyInvitationMail;
use Illuminate\Support\Facades\Mail;

class MailInvitationSender implements InvitationSender
{
    public function send(?string $phone, ?string $email, string $agencyName, string $link): bool
    {
        if ($email === null) {
            return false;
        }
        Mail::to($email)->send(new AgencyInvitationMail($agencyName, $link));

        return true;
    }
}
