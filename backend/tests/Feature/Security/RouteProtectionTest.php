<?php

use Database\Seeders\RolesSeeder;
use Illuminate\Support\Facades\Route;
use Laravel\Sanctum\Sanctum;

/**
 * Routes publiques VOULUES de l'API. Toute autre route doit être protégée.
 * Ajouter une route publique oblige à la déclarer ici : c'est la revue de sécurité.
 */
const PUBLIC_ROUTES = [
    'POST api/v1/auth/register', 'POST api/v1/auth/otp/send', 'POST api/v1/auth/otp/verify', 'POST api/v1/auth/password/reset', 'POST api/v1/auth/login',
    'GET api/v1/localities', 'GET api/v1/property-types', 'GET api/v1/amenities', 'GET api/v1/report-reasons',
    'GET api/v1/stays', 'GET api/v1/stays/{unit}', 'POST api/v1/webhooks/payments/{provider}', 'GET api/v1/listings', 'GET api/v1/listings/map', 'GET api/v1/listings/{identifier}', 'GET api/v1/listings/{identifier}/similar',
    'GET api/v1/passports/{reference}', 'GET api/v1/seo/sitemap', 'GET api/v1/agencies/{slug}', 'GET api/v1/plans', 'GET api/v1/premium-products', 'GET api/v1/featured',
];

/** @return list<array{string, string, bool}> [méthode, adresse remplie, vient du groupe admin] */
/** Les téléchargements signés (`*.download`) sont exclus des contrôles de rôle : la liaison du modèle précède la signature, un identifiant factice y répond 404. Leur protection est testée avec les documents. */
function apiRoutes(): array
{
    $rows = [];
    foreach (Route::getRoutes() as $route) {
        if (! str_starts_with($route->uri(), 'api/v1')) {
            continue;
        }
        foreach (array_diff($route->methods(), ['HEAD', 'OPTIONS']) as $method) {
            $uri = preg_replace_callback('/\{(\w+)\??\}/', function ($m) use ($route) {
                $where = $route->wheres[$m[1]] ?? '';

                return in_array($where, ['[0-9]+', '\d+'], true) ? '1' : 'x';
            }, $route->uri());
            $rows[] = ["{$method} {$route->uri()}", "/{$uri}", $method, str_starts_with($route->uri(), 'api/v1/admin') && ! str_ends_with((string) $route->getName(), '.download')];
        }
    }

    return $rows;
}

it('chaque route de l\'API est soit déclarée publique, soit refusée sans authentification (401)', function () {
    $checked = 0;
    foreach (apiRoutes() as [$id, $uri, $method]) {
        if (in_array($id, PUBLIC_ROUTES, true)) {
            continue;
        }
        $status = $this->json($method, $uri)->status();
        expect($status)->toBe(401, "{$id} devrait exiger une authentification (reçu {$status})");
        $checked++;
    }

    expect($checked)->toBeGreaterThan(100);
});

it('toutes les routes publiques déclarées existent encore (la liste blanche ne dérive pas)', function () {
    $ids = array_column(apiRoutes(), 0);

    foreach (PUBLIC_ROUTES as $declared) {
        expect($ids)->toContain($declared);
    }
});

it('chaque route d\'administration refuse un utilisateur ordinaire (403)', function () {
    $this->seed(RolesSeeder::class);
    Sanctum::actingAs(particulier());
    $checked = 0;

    foreach (apiRoutes() as [$id, $uri, $method, $admin]) {
        if (! $admin) {
            continue;
        }
        $status = $this->json($method, $uri, ['reason' => 'test test', 'note' => 'test test', 'message' => 'test test'])->status();
        expect($status)->toBe(403, "{$id} devrait refuser un non-administrateur (reçu {$status})");
        $checked++;
    }

    expect($checked)->toBeGreaterThan(50);
});

it('chaque route d\'administration refuse un administrateur sans second facteur, quand il est exigé', function () {
    config(['chezvous.admin.require_two_factor' => true]);
    Sanctum::actingAs(moderator('admin'));
    $checked = 0;

    foreach (apiRoutes() as [$id, $uri, $method, $admin]) {
        if (! $admin) {
            continue;
        }
        $res = $this->json($method, $uri, ['reason' => 'test test']);
        expect($res->status())->toBe(403, "{$id} accessible sans second facteur")->and($res->json('code'))->toBe('two_factor_setup_required');
        $checked++;
    }

    expect($checked)->toBeGreaterThan(50);
});

it('aucune route de l\'API n\'accepte les anciens verbes dangereux sur les ressources publiques', function () {
    foreach (['DELETE', 'PUT', 'PATCH'] as $method) {
        $this->json($method, '/api/v1/listings')->assertStatus(405);
        $this->json($method, '/api/v1/plans')->assertStatus(405);
    }
});
