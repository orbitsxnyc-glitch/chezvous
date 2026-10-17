<?php

use App\Console\Commands\Preflight;
use Illuminate\Support\Facades\Artisan;

/** Configuration de production valide : tout ce que la commande contrôle est conforme. */
function productionConfig(array $override = []): void
{
    config(array_merge([
        'app.env' => 'production', 'app.debug' => false, 'app.key' => 'base64:'.base64_encode(random_bytes(32)), 'app.url' => 'https://api.chezvous.sn',
        'chezvous.otp.driver' => 'mail', 'chezvous.agencies.invitation_driver' => 'mail', 'mail.default' => 'smtp', 'chezvous.admin.require_two_factor' => true,
        'session.secure' => true, 'session.http_only' => true, 'session.same_site' => 'lax', 'session.driver' => 'redis',
        'cors.allowed_origins' => ['https://chezvous.sn'], 'cors.supports_credentials' => true, 'sanctum.stateful' => ['chezvous.sn'],
        'queue.default' => 'redis', 'cache.default' => 'redis', 'database.default' => 'pgsql', 'sentry.dsn' => 'https://k@sentry.example/1', 'logging.default' => 'json',
        'chezvous.images.disk' => 's3',
    ], $override));
}

function failures(array $override = []): array
{
    // La connexion par défaut doit rester celle du test : sinon la transaction de RefreshDatabase n'est pas annulée.
    $default = config('database.default');
    productionConfig($override);

    try {
        return collect((new Preflight)->problems())->where(0, 'fail')->pluck(1)->all();
    } finally {
        config(['database.default' => $default]);
    }
}

it('accepte une configuration de production sûre', function () {
    productionConfig();

    $this->artisan('chezvous:preflight')->assertSuccessful();
});

it('refuse la configuration par défaut de développement', function () {
    $this->artisan('chezvous:preflight')->assertFailed();
});

it('chaque réglage dangereux fait échouer le contrôle', function (array $override, string $code) {
    expect(failures($override))->toContain($code);
})->with([
    'environnement' => [['app.env' => 'local'], 'app_env'],
    'débogage' => [['app.debug' => true], 'app_debug'],
    'clé vide' => [['app.key' => ''], 'app_key'],
    'URL non sécurisée' => [['app.url' => 'http://api.chezvous.sn'], 'app_url'],
    'OTP dans les journaux' => [['chezvous.otp.driver' => 'log'], 'otp_driver'],
    'invitations dans les journaux' => [['chezvous.agencies.invitation_driver' => 'log'], 'invitation_driver'],
    'courriel factice' => [['mail.default' => 'log'], 'mail'],
    'second facteur désactivé' => [['chezvous.admin.require_two_factor' => false], 'two_factor'],
    'cookie non sécurisé' => [['session.secure' => false], 'session_secure'],
    'cookie lisible par script' => [['session.http_only' => false], 'session_http_only'],
    'SameSite none' => [['session.same_site' => 'none'], 'session_same_site'],
    'session en tableau' => [['session.driver' => 'array'], 'session_driver'],
    'CORS ouvert' => [['cors.allowed_origins' => ['*']], 'cors_wildcard'],
    'CORS non https' => [['cors.allowed_origins' => ['http://chezvous.sn']], 'cors_https'],
    'CORS sans cookies' => [['cors.supports_credentials' => false], 'cors_credentials'],
    'domaines locaux' => [['sanctum.stateful' => ['localhost:3000']], 'sanctum_stateful'],
    'file synchrone' => [['queue.default' => 'sync'], 'queue_sync'],
    'cache local' => [['cache.default' => 'array'], 'cache_store'],
    'base non PostgreSQL' => [['database.default' => 'sqlite'], 'database'],
]);

it('les avis (suivi d\'erreurs, canaux factices, stockage local) n\'empêchent pas le déploiement mais sont signalés', function () {
    productionConfig(['sentry.dsn' => null, 'chezvous.images.disk' => 'public']);
    $codes = collect((new Preflight)->problems())->where(0, 'warn')->pluck(1)->all();

    expect($codes)->toContain('sentry')->toContain('sms_stub')->toContain('whatsapp_stub')->toContain('push_stub')->toContain('media_storage')->toContain('phone_unproven');
    $this->artisan('chezvous:preflight')->assertSuccessful();
});

it('produit une sortie JSON exploitable', function () {
    productionConfig(['app.debug' => true]);

    expect(Artisan::call('chezvous:preflight', ['--json' => true]))->toBe(1);

    $report = json_decode(Artisan::output(), true);
    expect($report['ok'])->toBeFalse()->and(collect($report['problems'])->pluck('code'))->toContain('app_debug');
});
