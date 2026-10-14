<?php

namespace App\Console\Commands;

use App\Services\Commercial\PremiumService;
use App\Services\Commercial\SubscriptionService;
use Illuminate\Console\Command;

class RunCommercial extends Command
{
    protected $signature = 'commercial:run {--expire : expirations} {--remind : rappels d\'expiration} {--enforce : mise en pause après le délai de grâce}';

    protected $description = 'Tâches planifiées de l\'offre commerciale : expirations, rappels à 7 et 1 jour, application des quotas, fin des mises en avant.';

    public function handle(SubscriptionService $subscriptions, PremiumService $premium): int
    {
        $all = ! ($this->option('expire') || $this->option('remind') || $this->option('enforce'));

        if ($all || $this->option('expire')) {
            $this->info('Souscriptions expirées : '.$subscriptions->expireDue());
            $this->info('Mises en avant expirées : '.$premium->expireDue());
        }
        if ($all || $this->option('remind')) {
            $this->info('Rappels d\'expiration : '.$subscriptions->remindExpiring());
        }
        if ($all || $this->option('enforce')) {
            $this->info('Annonces mises en pause : '.$subscriptions->enforceDowngrades());
        }

        return self::SUCCESS;
    }
}
