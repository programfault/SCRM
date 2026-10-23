<?php

namespace App\Services\Customers;

class PhoneNumbers
{
    public static function normalize(string $value): ?string
    {
        $digits = preg_replace('/\D+/', '', $value) ?? '';

        if (str_starts_with($digits, '86') && strlen($digits) === 13) {
            $digits = substr($digits, 2);
        }

        return preg_match('/^1\d{10}$/', $digits) === 1 ? $digits : null;
    }
}
