<?php

namespace App\Console\Commands;

use App\Services\Stays\BookingService;
use App\Services\Stays\OutboxService;
use Illuminate\Console\Command;

class RunStays extends Command
{
    protected $signature = 'stays:run {--expire : expirations (demandes sans réponse, verrous échus)} {--outbox : diffusion des événements en attente}';

    protected $description = 'Tâches planifiées des séjours : expiration des demandes et des verrous, diffusion des notifications (outbox).';

    public function handle(BookingService $bookings, OutboxService $outbox): int
    {
        $all = ! ($this->option('expire') || $this->option('outbox'));
        if ($all || $this->option('expire')) {
            $this->info('Séjours expirés : '.$bookings->expireDue());
        }
        if ($all || $this->option('outbox')) {
            $this->info('Événements diffusés : '.$outbox->process());
        }

        return self::SUCCESS;
    }
}
