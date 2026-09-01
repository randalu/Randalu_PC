<?php

namespace App\Support;

class SriLankanPhone
{
    public static function normalize(?string $phone): ?string
    {
        if ($phone === null) {
            return null;
        }

        $digits = preg_replace('/\D+/', '', $phone);

        if ($digits === '') {
            return null;
        }

        if (str_starts_with($digits, '94') && strlen($digits) === 11) {
            return '+'.$digits;
        }

        if (str_starts_with($digits, '0') && strlen($digits) === 10) {
            return '+94'.substr($digits, 1);
        }

        if (strlen($digits) === 9 && str_starts_with($digits, '7')) {
            return '+94'.$digits;
        }

        return null;
    }

    public static function same(?string $first, ?string $second): bool
    {
        $first = self::normalize($first);
        $second = self::normalize($second);

        return $first !== null && $first === $second;
    }
}
