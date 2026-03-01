<?php
declare(strict_types=1);

namespace PassApp\Security;

use PassApp\Core\SessionVault;

final class CsrfShield
{
    private const KEY = '__csrf_token';

    public static function token(): string
    {
        $t = SessionVault::pull(self::KEY);
        if (is_string($t) && $t !== '') {
            return $t;
        }
        $t = bin2hex(random_bytes(32));
        SessionVault::put(self::KEY, $t);
        return $t;
    }

    public static function assert(?string $token): void
    {
        $expected = SessionVault::pull(self::KEY);
        if (!is_string($expected) || $expected === '' || !is_string($token) || !hash_equals($expected, $token)) {
            http_response_code(419);
            echo 'CSRF 토큰이 유효하지 않습니다.';
            exit;
        }
    }
}
