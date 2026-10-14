<?php

namespace App\Logging;

use Monolog\LogRecord;
use Monolog\Processor\ProcessorInterface;

/**
 * Masque les données sensibles dans chaque ligne de journal : e-mails, numéros de téléphone, jetons Bearer,
 * et toute valeur dont la clé évoque un secret (mot de passe, jeton, code, OTP...).
 */
class RedactionProcessor implements ProcessorInterface
{
    private const SECRET_KEYS = '/pass(word)?|secret|token|authorization|cookie|otp|^code$|recovery|api[_-]?key|csrf|xsrf|signature|two_factor/i';

    public function __invoke(LogRecord $record): LogRecord
    {
        return $record->with(
            message: $this->scrubString($record->message),
            context: $this->scrubArray($record->context),
            extra: $this->scrubArray($record->extra),
        );
    }

    public function scrubString(string $text): string
    {
        $text = preg_replace('/Bearer\s+[A-Za-z0-9._\-|]+/i', 'Bearer [jeton]', $text) ?? $text;
        $text = preg_replace('/[\w.+-]+@[\w-]+\.[\w.-]+/', '[email]', $text) ?? $text;

        return preg_replace('/\+?221[\s.-]?[37]\d(?:[\s.-]?\d){7}(?!\d)|(?<!\d)[37]\d(?:[\s.-]?\d){7}(?!\d)/', '[téléphone]', $text) ?? $text;
    }

    /**
     * @param  array<array-key, mixed>  $data
     * @return array<array-key, mixed>
     */
    public function scrubArray(array $data): array
    {
        foreach ($data as $key => $value) {
            if (is_string($key) && preg_match(self::SECRET_KEYS, $key)) {
                $data[$key] = '[masqué]';
            } elseif (is_array($value)) {
                $data[$key] = $this->scrubArray($value);
            } elseif (is_string($value)) {
                $data[$key] = $this->scrubString($value);
            } elseif ($value instanceof \Throwable) {
                $data[$key] = class_basename($value).' : '.$this->scrubString($value->getMessage());
            }
        }

        return $data;
    }
}
