<?php

namespace App\Console\Commands;

use App\Services\Passport\FreshnessService;
use App\Services\Search\ListingCounters;
use Illuminate\Console\Command;

class MaintainSearch extends Command
{
    protected $signature = 'listings:maintain {--counters : écrit seulement les compteurs} {--freshness : recalcule seulement la fraîcheur}';

    protected $description = 'Recalcule la fraîcheur des annonces et écrit les compteurs de vues en base.';

    public function handle(FreshnessService $freshness, ListingCounters $counters): int
    {
        $both = ! $this->option('counters') && ! $this->option('freshness');
        if ($both || $this->option('freshness')) {
            $this->info('Fraîcheur : '.$freshness->refreshAll().' annonce(s) mise(s) à jour.');
        }
        if ($both || $this->option('counters')) {
            $this->info('Compteurs : '.$counters->flush().' valeur(s) écrite(s).');
        }

        return self::SUCCESS;
    }
}
