<?php

namespace App\Support;

use App\Logging\RedactionProcessor;
use Sentry\Event;
use Sentry\EventHint;

/**
 * Nettoie chaque événement avant son envoi au suivi d'erreurs : aucune donnée personnelle ni secret ne doit quitter l'application.
 */
class SentryScrubber
{
    public static function scrub(Event $event, ?EventHint $hint = null): ?Event
    {
        $redactor = new RedactionProcessor;

        $event->setRequest([]); // ni corps, ni en-têtes, ni cookies, ni query string
        $event->setUser(null);
        $event->setMessage($redactor->scrubString((string) $event->getMessage()));
        foreach ($event->getExceptions() as $exception) {
            $exception->setValue($redactor->scrubString($exception->getValue()));
        }
        $event->setExtra($redactor->scrubArray($event->getExtra()));
        foreach ($event->getBreadcrumbs() as $crumb) {
            // Les chemins seuls sont conservés ; leurs données associées sont vidées.
            $event->setBreadcrumb([]);
        }

        return $event;
    }
}
