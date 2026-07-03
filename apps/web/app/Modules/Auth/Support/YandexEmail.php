<?php

namespace App\Modules\Auth\Support;

use Illuminate\Support\Str;

final class YandexEmail
{
    public static function canonicalize(string $email): string
    {
        $email = Str::lower(trim($email));
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, null);

        if ($localPart !== null && $domain === 'yandex.com') {
            return "{$localPart}@yandex.ru";
        }

        return $email;
    }

    /**
     * @return list<string>
     */
    public static function candidates(string $email): array
    {
        $email = self::canonicalize($email);
        $candidates = [$email];
        [$localPart, $domain] = array_pad(explode('@', $email, 2), 2, null);

        if ($localPart !== null && in_array($domain, ['yandex.ru', 'yandex.com'], true)) {
            $candidates[] = "{$localPart}@yandex.ru";
            $candidates[] = "{$localPart}@yandex.com";
        }

        return array_values(array_unique($candidates));
    }
}