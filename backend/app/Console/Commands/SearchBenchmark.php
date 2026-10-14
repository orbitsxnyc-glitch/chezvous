<?php

namespace App\Console\Commands;

use Illuminate\Console\Command;
use Illuminate\Http\Request;
use Illuminate\Support\Facades\DB;

/**
 * Mesure les temps de réponse (pile HTTP complète, sans réseau) de la recherche publique.
 * Local uniquement : à lancer après `demo:listings`. Cible de la spec : p95 < 400 ms sur 10 000 annonces.
 */
class SearchBenchmark extends Command
{
    protected $signature = 'search:benchmark {--runs=100}';

    protected $description = 'Mesure p50/p95 de la recherche publique (environnement local).';

    public function handle(): int
    {
        if (! app()->environment('local')) {
            $this->error('Commande réservée à l\'environnement local.');

            return self::FAILURE;
        }
        config(['chezvous.search.per_minute' => 100000]);

        $ulid = DB::table('listings')->where('status', 'published')->where('title', 'like', '[DÉMO]%')->value('ulid');
        $total = DB::table('listings')->where('status', 'published')->count();
        $this->info("Annonces publiées : {$total}");

        $cases = [
            'liste (pertinence)' => '/api/v1/listings',
            'filtres combinés' => '/api/v1/listings?transaction=rent_long&price_max=500000&locality=yoff&bedrooms_min=3',
            'quartier + descendants' => '/api/v1/listings?locality=dakar-department&sort=price_asc',
            'texte (accents)' => '/api/v1/listings?q=sacre',
            'tri prix décroissant' => '/api/v1/listings?type=apartment&sort=price_desc',
            'carte (emprise)' => '/api/v1/listings/map?bbox=-17.52,14.70,-17.45,14.78',
            'carte + filtres' => '/api/v1/listings/map?bbox=-17.52,14.70,-17.45,14.78&price_max=400000&transaction=rent_long',
            'fiche' => "/api/v1/listings/{$ulid}",
            'similaires' => "/api/v1/listings/{$ulid}/similar",
        ];
        $runs = (int) $this->option('runs');
        $rows = [];
        $worst = 0.0;

        foreach ($cases as $label => $uri) {
            $times = [];
            for ($i = 0; $i < $runs + 5; $i++) {
                $start = hrtime(true);
                $response = app()->handle(Request::create($uri, 'GET', [], [], [], ['REMOTE_ADDR' => '127.0.0.1']));
                if ($i >= 5) { // 5 requêtes d'échauffement écartées
                    $times[] = (hrtime(true) - $start) / 1e6;
                }
                if ($response->getStatusCode() !== 200) {
                    $this->error("{$label} : statut {$response->getStatusCode()}");

                    return self::FAILURE;
                }
            }
            sort($times);
            $p95 = $times[(int) floor(0.95 * (count($times) - 1))];
            $worst = max($worst, $p95);
            $rows[] = [$label, round($times[(int) floor(0.5 * (count($times) - 1))], 1), round($p95, 1), round(max($times), 1)];
        }

        $this->table(['Requête', 'p50 (ms)', 'p95 (ms)', 'max (ms)'], $rows);
        $this->line($worst < 400 ? '<info>Cible p95 < 400 ms atteinte (pire p95 : '.round($worst, 1).' ms).</info>' : '<error>Cible p95 < 400 ms NON atteinte (pire p95 : '.round($worst, 1).' ms).</error>');

        return $worst < 400 ? self::SUCCESS : self::FAILURE;
    }
}
