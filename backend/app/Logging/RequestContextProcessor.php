<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/** Ajoute à chaque ligne l'identifiant de requête, la méthode, le chemin (sans paramètres) et l'identifiant interne de l'utilisateur. */
class RequestContextProcessor implements ProcessorInterface
{
    public function __invoke(LogRecord $record): LogRecord
    {
        if (app()->runningInConsole() && ! app()->bound('request')) {
            return $record;
        }
        $request = request();
        $extra = [
            'request_id' => $request->attributes->get('request_id'),
            'method' => $request->method(),
            'path' => '/'.ltrim($request->path(), '/'), // jamais la query string : elle peut contenir un jeton
            'user_id' => $request->user('sanctum')?->getKey(),
        ];

        return $record->with(extra: array_merge($record->extra, array_filter($extra, fn ($v) => $v !== null)));
    }
}
