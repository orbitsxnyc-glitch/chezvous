<?php

namespace App\Console\Commands;

use App\Services\Engagement\AlertService;
use App\Services\Engagement\FavoriteService;
use App\Services\Inquiries\ReminderService;
use Illuminate\Console\Command;

class RunEngagement extends Command
{
    protected $signature = 'engagement:run {--visits : rappels de visite} {--unanswered : demandes sans réponse} {--favorites : surveillance des favoris} {--alerts : alertes de recherche}';

    protected $description = 'Tâches planifiées de la Phase 5 : rappels, relances, favoris et alertes.';

    public function handle(ReminderService $reminders, FavoriteService $favorites, AlertService $alerts): int
    {
        $all = ! ($this->option('visits') || $this->option('unanswered') || $this->option('favorites') || $this->option('alerts'));

        if ($all || $this->option('visits')) {
            $this->info('Rappels de visite : '.$reminders->remindVisits());
        }
        if ($all || $this->option('unanswered')) {
            $this->info('Relances sans réponse : '.$reminders->remindUnanswered());
        }
        if ($all || $this->option('favorites')) {
            $r = $favorites->watch();
            $this->info("Favoris : {$r['price_drops']} baisse(s) de prix, {$r['unavailable']} indisponible(s)");
        }
        if ($all || $this->option('alerts')) {
            $this->info('Alertes de recherche : '.$alerts->dispatch());
        }

        return self::SUCCESS;
    }
}
