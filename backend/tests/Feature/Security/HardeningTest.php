<?php

use App\Logging\RedactionProcessor;
use App\Support\SentryScrubber;
use Illuminate\Support\Facades\Log;
use Laravel\Sanctum\Sanctum;
use Monolog\Level;
use Monolog\LogRecord;
use Sentry\Event;
use Sentry\UserDataBag;

it('ajoute les en-têtes de sécurité à toutes les réponses de l\'API, y compris les erreurs', function (string $method, string $uri, int $status) {
    $res = $this->json($method, $uri)->assertStatus($status);

    expect($res->headers->get('X-Content-Type-Options'))->toBe('nosniff')->and($res->headers->get('X-Frame-Options'))->toBe('DENY')
        ->and($res->headers->get('Referrer-Policy'))->toBe('strict-origin-when-cross-origin')
        ->and($res->headers->get('Content-Security-Policy'))->toContain("default-src 'none'")->toContain("frame-ancestors 'none'")
        ->and($res->headers->get('Permissions-Policy'))->toContain('camera=()')->toContain('geolocation=()');
})->with([['GET', '/api/v1/localities', 200], ['GET', '/api/v1/me', 401], ['GET', '/api/v1/inconnue', 404], ['POST', '/api/v1/auth/login', 422]]);

it('HSTS seulement en HTTPS ou en production', function () {
    $plain = $this->getJson('/api/v1/localities');
    $secure = $this->withServerVariables(['HTTPS' => 'on'])->getJson('https://localhost/api/v1/localities');

    expect($plain->headers->has('Strict-Transport-Security'))->toBeFalse()
        ->and($secure->headers->get('Strict-Transport-Security'))->toContain('max-age=31536000')->toContain('includeSubDomains');
});

it('les réponses d\'un utilisateur connecté ne sont jamais mises en cache partagé', function () {
    Sanctum::actingAs(particulier());

    $res = $this->getJson('/api/v1/me')->assertOk();

    expect($res->headers->get('Cache-Control'))->toContain('no-store')->toContain('private');
});

it('attribue un identifiant de requête, accepte un identifiant valide et rejette un identifiant dangereux', function () {
    $generated = $this->getJson('/api/v1/localities')->headers->get('X-Request-Id');
    $kept = $this->getJson('/api/v1/localities', ['X-Request-Id' => 'req-12345678'])->headers->get('X-Request-Id');
    $bad = $this->getJson('/api/v1/localities', ['X-Request-Id' => "x\r\nSet-Cookie: a=b"])->headers->get('X-Request-Id');

    expect($generated)->toHaveLength(26)->and($kept)->toBe('req-12345678')->and($bad)->not->toContain('Set-Cookie')->toHaveLength(26);
});

it('CORS : seule l\'origine configurée est autorisée, jamais « * », avec cookies', function () {
    config(['cors.allowed_origins' => ['https://chezvous.sn']]);
    $headers = fn (string $origin) => ['Origin' => $origin, 'Access-Control-Request-Method' => 'POST'];

    $ok = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], $this->transformHeadersToServerVars($headers('https://chezvous.sn')));
    $evil = $this->call('OPTIONS', '/api/v1/auth/login', [], [], [], $this->transformHeadersToServerVars($headers('https://evil.example')));

    expect($ok->headers->get('Access-Control-Allow-Origin'))->toBe('https://chezvous.sn')->and($ok->headers->get('Access-Control-Allow-Credentials'))->toBe('true')
        ->and($evil->headers->get('Access-Control-Allow-Origin'))->not->toBe('https://evil.example')->not->toBe('*');
});

// ------------------------------------------------------------ journaux sans donnée sensible

function logRecord(string $message, array $context = []): LogRecord
{
    return new LogRecord(new DateTimeImmutable, 'test', Level::Info, $message, $context);
}

it('masque e-mails, téléphones et jetons dans les messages de journal', function () {
    $out = (new RedactionProcessor)(logRecord('Connexion de awa@example.com au +221 77 123 45 67 avec Bearer 12|abcDEF123xyz puis 771234567'));

    expect($out->message)->not->toContain('awa@example.com')->not->toContain('123 45 67')->not->toContain('abcDEF123xyz')->not->toContain('771234567')
        ->toContain('[email]')->toContain('[téléphone]')->toContain('Bearer [jeton]');
});

it('masque les valeurs dont la clé évoque un secret, à tous les niveaux du contexte', function () {
    $out = (new RedactionProcessor)(logRecord('x', ['user' => ['id' => 5, 'password' => 'secret123', 'otp' => '123456', 'nested' => ['api_key' => 'k', 'Authorization' => 'Bearer z']], 'token' => 'abc', 'note' => 'écrire à bob@mail.sn']));

    expect($out->context['user']['id'])->toBe(5)->and($out->context['user']['password'])->toBe('[masqué]')->and($out->context['user']['otp'])->toBe('[masqué]')
        ->and($out->context['user']['nested']['api_key'])->toBe('[masqué]')->and($out->context['token'])->toBe('[masqué]')->and($out->context['note'])->toBe('écrire à [email]');
});

it('masque les messages d\'exception placés dans le contexte', function () {
    $out = (new RedactionProcessor)(logRecord('échec', ['exception' => new RuntimeException('SMS refusé pour +221771234567')]));

    expect($out->context['exception'])->toBe('RuntimeException : SMS refusé pour [téléphone]');
});

it('le canal « json » écrit une ligne JSON valide et masquée', function () {
    config(['logging.channels.json.handler_with' => ['stream' => $file = tempnam(sys_get_temp_dir(), 'log')]]);

    Log::channel('json')->info('Alerte pour awa@example.com', ['password' => 'x', 'listing' => 'CV-1']);

    $line = json_decode(trim((string) file_get_contents($file)), true);
    expect($line['message'])->toBe('Alerte pour [email]')->and($line['context']['password'])->toBe('[masqué]')->and($line['context']['listing'])->toBe('CV-1');
    unlink($file);
});

it('le suivi d\'erreurs n\'envoie ni requête, ni utilisateur, ni donnée personnelle', function () {
    $event = Event::createEvent();
    $event->setMessage('Échec pour awa@example.com +221771234567');
    $event->setRequest(['url' => 'https://x/api?token=abc', 'data' => ['password' => 'p'], 'cookies' => ['s' => 'v'], 'headers' => ['Authorization' => 'Bearer z']]);
    $event->setUser(new UserDataBag('5', 'awa@example.com', '41.82.1.1'));
    $event->setExtra(['phone' => '+221771234567', 'listing' => 'CV-1', 'note' => 'écrire à bob@mail.sn']);

    $out = SentryScrubber::scrub($event);

    expect($out->getRequest())->toBe([])->and($out->getUser())->toBeNull()->and($out->getMessage())->toBe('Échec pour [email] [téléphone]')
        ->and($out->getExtra()['phone'])->toBe('[téléphone]')->and($out->getExtra()['listing'])->toBe('CV-1')->and($out->getExtra()['note'])->toBe('écrire à [email]');
});

it('le suivi d\'erreurs est inactif sans DSN et n\'envoie jamais de données personnelles par défaut', function () {
    expect(config('sentry.dsn'))->toBeNull()->and(config('sentry.send_default_pii'))->toBeFalse()->and(config('sentry.before_send'))->toBe([SentryScrubber::class, 'scrub']);
});

// ------------------------------------------------------------ supervision des files (Horizon)

it('Horizon est refusé aux visiteurs, aux utilisateurs ordinaires et aux administrateurs simples', function () {
    $this->get('/horizon')->assertStatus(403);
    $this->actingAs(particulier(), 'web')->get('/horizon')->assertStatus(403);
    $this->actingAs(moderator('admin'), 'web')->get('/horizon')->assertStatus(403);
});

it('Horizon exige le second facteur validé du super administrateur', function () {
    config(['chezvous.admin.require_two_factor' => true]);
    $super = moderator('super_admin');
    $this->actingAs($super, 'web')->get('/horizon')->assertStatus(403);              // second facteur non activé

    config(['chezvous.admin.require_two_factor' => false]);
    $this->actingAs($super, 'web')->get('/horizon')->assertOk();                      // second facteur non exigé (développement)
});
