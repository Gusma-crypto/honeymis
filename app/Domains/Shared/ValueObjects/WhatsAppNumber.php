<?php

namespace App\Domains\Shared\ValueObjects;

final class WhatsAppNumber
{
    public static function normalize(string $value): ?string
    {
        $number = preg_replace('/[\s().-]+/', '', trim($value)) ?? '';
        if (str_starts_with($number, '+')) {
            $number = substr($number, 1);
        }
        if (str_starts_with($number, '0')) {
            $number = '62'.substr($number, 1);
        }

        return preg_match('/^[1-9][0-9]{8,14}$/D', $number) === 1 ? $number : null;
    }
}
