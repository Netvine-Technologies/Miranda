<?php

namespace App\Support;

class PhoneNumberFormatter
{
    /**
     * @return array{main: string, extension: string|null}
     */
    public static function split(?string $phoneNumber): array
    {
        $value = trim((string) $phoneNumber);
        $extension = null;

        if (preg_match('/(?:;\s*ext\s*=\s*|\b(?:ext(?:ension)?\.?|x|#)\s*)(\d{1,6})\s*$/i', $value, $match, PREG_OFFSET_CAPTURE) === 1) {
            $extension = (string) $match[1][0];
            $value = substr($value, 0, (int) $match[0][1]);
        }

        $main = preg_replace('/[^0-9+]/', '', $value) ?? '';
        $main = preg_replace('/(?!^)\+/', '', $main) ?? $main;

        return ['main' => $main, 'extension' => $extension];
    }

    public static function dialable(?string $phoneNumber): string
    {
        return self::split($phoneNumber)['main'];
    }

    public static function telUri(?string $phoneNumber): string
    {
        $parts = self::split($phoneNumber);

        return $parts['main'].($parts['extension'] !== null ? ';ext='.$parts['extension'] : '');
    }

    public static function comparisonKey(?string $phoneNumber): string
    {
        $digits = preg_replace('/\D+/', '', self::split($phoneNumber)['main']) ?? '';

        return str_starts_with($digits, '00') ? substr($digits, 2) : $digits;
    }
}
