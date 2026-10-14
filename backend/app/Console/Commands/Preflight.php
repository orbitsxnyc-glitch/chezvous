<?php

namespace App\Console\Commands;

use App\Services\Notifications\Channels\PushChannel;
use App\Services\Notifications\Channels\SmsChannel;
use App\Services\Notifications\Channels\WhatsAppChannel;
use Illuminate\Console\Command;

/**
 * Contrôle de configuration avant un déploiement : refuse les réglages de développement en production
 * (débogage, OTP dans les journaux, courriels factices, second facteur désactivé, cookies non sécurisés...).
 * Code de sortie non nul si un point ÉCHOUE ; les avertissements n'empêchent pas le déploiement.
 */
class Preflight extends Command
{
    protected $signature = 'chezvous:preflight {--json : sortie JSON}';

    protected $description = 'Vérifie que la configuration est sûre pour la production.';

    /** @return list<array{string, string, string}> [niveau (fail|warn), code, message] des problèmes détectés */
    public function problems(): array
    {
        $out = [];
        $fail = function (bool $bad, string $code, string $message) use (&$out) {
            if ($bad) {
                $out[] = ['fail', $code, $message];
            }
        };
        $warn = function (bool $bad, string $code, string $message) use (&$out) {
            if ($bad) {
                $out[] = ['warn', $code, $message];
            }
        };

        $fail(config('app.env') !== 'production', 'app_env', 'APP_ENV doit valoir « production ».');
        $fail((bool) config('app.debug'), 'app_debug', 'APP_DEBUG doit être faux : il expose le code et les variables d\'environnement.');
        $fail(blank(config('app.key')), 'app_key', 'APP_KEY est vide.');
        $fail(! str_starts_with((string) config('app.url'), 'https://'), 'app_url', 'APP_URL doit être en https://.');

        $fail(config('chezvous.otp.driver') === 'log', 'otp_driver', 'Les codes OTP seraient écrits dans les journaux : choisir un canal réel (CHEZVOUS_OTP_DRIVER).');
        $fail(config('chezvous.agencies.invitation_driver') === 'log', 'invitation_driver', 'Les invitations d\'agents seraient écrites dans les journaux (CHEZVOUS_INVITATION_DRIVER).');
        $fail(in_array(config('mail.default'), ['log', 'array'], true), 'mail', 'Aucun courriel réel ne serait envoyé (MAIL_MAILER).');
        $fail(! config('chezvous.admin.require_two_factor'), 'two_factor', 'La double authentification des administrateurs doit être obligatoire (CHEZVOUS_ADMIN_REQUIRE_2FA).');

        $fail(! config('session.secure'), 'session_secure', 'Le cookie de session doit être « secure » (SESSION_SECURE_COOKIE=true).');
        $fail(! config('session.http_only'), 'session_http_only', 'Le cookie de session doit être « HttpOnly ».');
        $fail(! in_array(config('session.same_site'), ['lax', 'strict'], true), 'session_same_site', 'SameSite doit valoir lax ou strict.');
        $fail(in_array(config('session.driver'), ['array', 'file', 'cookie'], true), 'session_driver', 'Pilote de session inadapté (utiliser redis ou database).');

        $origins = (array) config('cors.allowed_origins');
        $fail($origins === [] || in_array('*', $origins, true), 'cors_wildcard', 'CORS ne doit jamais autoriser « * » avec des cookies.');
        $fail(collect($origins)->contains(fn ($o) => $o !== '*' && ! str_starts_with($o, 'https://')), 'cors_https', 'Les origines CORS doivent être en https://.');
        $fail(! config('cors.supports_credentials'), 'cors_credentials', 'Les cookies de session exigent supports_credentials=true.');
        $stateful = (array) config('sanctum.stateful');
        $fail($stateful === [] || collect($stateful)->contains(fn ($d) => str_contains($d, 'localhost') || str_contains($d, '127.0.0.1')), 'sanctum_stateful', 'SANCTUM_STATEFUL_DOMAINS doit lister les domaines de production, pas localhost.');

        $fail(config('queue.default') === 'sync', 'queue_sync', 'La file « sync » bloque les requêtes et perd les relances : utiliser redis.');
        $fail(in_array(config('cache.default'), ['array', 'file'], true), 'cache_store', 'Le cache doit être partagé (redis) : les limiteurs de débit et les droits en dépendent.');
        $fail(config('database.default') !== 'pgsql', 'database', 'PostgreSQL (avec PostGIS) est requis.');

        $warn(blank(config('sentry.dsn')), 'sentry', 'Suivi d\'erreurs non configuré (SENTRY_LARAVEL_DSN).');
        $warn(config('logging.default') !== 'json' && ! in_array('json', (array) config('logging.channels.stack.channels', []), true), 'log_json', 'Journaux non structurés : préférer LOG_CHANNEL=json.');
        $warn(config('chezvous.images.disk') === 'public' && config('filesystems.disks.public.driver') === 'local', 'media_storage', 'Les médias sont sur le disque local : choisir un stockage compatible S3 avant la production.');
        $warn(config('notifications.drivers.sms') === SmsChannel::class, 'sms_stub', 'Canal SMS factice : aucun SMS n\'est envoyé.');
        $warn(config('notifications.drivers.whatsapp') === WhatsAppChannel::class, 'whatsapp_stub', 'Canal WhatsApp factice : aucun message n\'est envoyé.');
        $warn(config('notifications.drivers.push') === PushChannel::class, 'push_stub', 'Canal push factice : aucune notification push n\'est envoyée.');

        return $out;
    }

    public function handle(): int
    {
        $problems = $this->problems();
        $failures = array_filter($problems, fn ($p) => $p[0] === 'fail');

        if ($this->option('json')) {
            $this->line((string) json_encode(['ok' => $failures === [], 'problems' => array_map(fn ($p) => ['level' => $p[0], 'code' => $p[1], 'message' => $p[2]], $problems)], JSON_UNESCAPED_UNICODE));
        } else {
            foreach ($problems as [$level, $code, $message]) {
                $level === 'fail' ? $this->error("ÉCHEC  {$code} : {$message}") : $this->warn("AVIS   {$code} : {$message}");
            }
            $failures === [] ? $this->info('Configuration prête pour la production'.($problems === [] ? '.' : ' (avec avis).')) : $this->error(count($failures).' point(s) bloquant(s).');
        }

        return $failures === [] ? self::SUCCESS : self::FAILURE;
    }
}
