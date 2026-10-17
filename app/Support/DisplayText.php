<?php

namespace App\Support;

class DisplayText
{
    public static function plain(mixed $value, string $fallback = ''): string
    {
        $text = is_scalar($value) ? (string) $value : '';

        if ($text === '') {
            return $fallback;
        }

        for ($i = 0; $i < 3; $i++) {
            $decoded = html_entity_decode($text, ENT_QUOTES | ENT_HTML5, 'UTF-8');

            if ($decoded === $text) {
                break;
            }

            $text = $decoded;
        }

        $text = strip_tags($text);
        $text = preg_replace('/\s+/u', ' ', $text) ?? $text;
        $text = trim($text);

        return $text !== '' ? $text : $fallback;
    }
}
