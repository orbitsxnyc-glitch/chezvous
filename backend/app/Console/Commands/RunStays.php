<?php

namespace App\Console\Commands;

use App\Services\Stays\BookingService;
use App\Services\Stays\OutboxService;
use App\Services\Stays\UnitService;
use Illuminate\Console\Command;

class RunStays extends Command
{
    protected $signature = 'stays:run {--expire : expirations (demandes sans réponse, verrous échus)} {--outbox : diffusion des événements en attente} {--complete : fin automatique des séjours terminés} {--checks : retrait des unités dont un contrôle exigé a expiré}';

    protected $description = 'Tâches planifiées des séjours : expiration des demandes et des verrous, diffusion des notifications (outbox).';

    public function handle(BookingService $bookings, OutboxService $outbox, UnitService $units): int
    {
        $all = ! ($this->option('expire') || $this->option('outbox') || $this->option('complete') || $this->option('checks'));
        if ($all || $this->option('expire')) {
            $this->info('Séjours expirés : '.$bookings->expireDue());
        }
        if ($all || $this->option('complete')) {
            $this->info('Séjours terminés : '.$bookings->completeDue());
        }
        if ($all || $this->option('checks')) {
            $this->info('Unités retirées (contrôle expiré) : '.$units->revokeForExpiredChecks());
        }
        if ($all || $this->option('outbox')) {
            $this->info('Événements diffusés : '.$outbox->process());
        }

        return self::SUCCESS;
    }
}
