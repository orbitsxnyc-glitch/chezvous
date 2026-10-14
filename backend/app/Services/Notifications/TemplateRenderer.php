<?php

namespace App\Services\Notifications;

use App\Models\NotificationTemplate;
use Illuminate\Support\Carbon;

/**
 * Modèles versionnés en français (NOT-05). Seules les variables déclarées pour le type sont remplaçables ;
 * une variable absente devient vide. Aucune coordonnée personnelle n'entre jamais dans un message.
 */
class TemplateRenderer
{
    /**
     * @param  array<string, mixed>  $data
     * @return array{subject: string|null, body: string}|null null si aucun modèle actif
     */
    public function render(string $type, string $channel, array $data, string $locale = 'fr'): ?array
    {
        $template = $this->active($type, $channel, $locale);
        if ($template === null) {
            return null;
        }
        $vars = $this->variables($type, $data);

        return ['subject' => $template->subject !== null ? $this->fill($template->subject, $vars) : null, 'body' => $this->fill($template->body, $vars)];
    }

    public function active(string $type, string $channel, string $locale = 'fr'): ?NotificationTemplate
    {
        return NotificationTemplate::where(compact('type', 'channel', 'locale'))->where('is_active', true)->orderByDesc('version')->first();
    }

    /** @return list<string> variables {nom} utilisées dans un texte */
    public function placeholders(string $text): array
    {
        preg_match_all('/\{([a-z_]+)\}/', $text, $m);

        return array_values(array_unique($m[1]));
    }

    /**
     * Variables des modèles, dérivées des données de l'événement.
     *
     * @param  array<string, mixed>  $data
     * @return array<string, string>
     */
    public function variables(string $type, array $data): array
    {
        $money = fn ($v) => $v === null || $v === '' ? '' : number_format((int) $v, 0, ',', ' ').' FCFA';
        $date = fn ($v) => $v ? Carbon::parse((string) $v)->timezone('Africa/Dakar')->translatedFormat('d/m/Y à H\\hi') : '';

        $all = [
            'listing' => $data['listing'] ?? '',
            'reason' => $data['reason'] ?? '',
            'when' => $date($data['scheduled_at'] ?? null),
            'hours' => $data['hours_before'] ?? '',
            'old_price' => $money($data['old_price_xof'] ?? null),
            'new_price' => $money($data['new_price_xof'] ?? null),
            'name' => $data['name'] ?? '',
            'count' => $data['count'] ?? '',
            'plan' => $data['plan'] ?? '',
            'days' => $data['days'] ?? '',
            'ends_at' => $date($data['ends_at'] ?? null),
            'grace_until' => $date($data['grace_until'] ?? null),
            'listings_paused' => $data['listings_paused'] ?? '',
        ];
        $allowed = NotificationCatalog::vars($type);

        return array_map(fn ($v) => $this->clean((string) $v), array_intersect_key($all, array_flip($allowed)));
    }

    /** @param array<string, string> $vars */
    private function fill(string $text, array $vars): string
    {
        return (string) preg_replace_callback('/\{([a-z_]+)\}/', fn ($m) => $vars[$m[1]] ?? '', $text);
    }

    /** Texte brut sur une seule ligne, de longueur limitée (le contenu saisi par un utilisateur ne doit rien casser). */
    private function clean(string $value): string
    {
        return mb_substr(trim((string) preg_replace('/[\x00-\x1F\x7F]+/u', ' ', $value)), 0, 120);
    }
}
