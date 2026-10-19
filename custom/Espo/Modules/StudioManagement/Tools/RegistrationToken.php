<?php

namespace Espo\Modules\StudioManagement\Tools;

final class RegistrationToken
{
    private const CURRENT_PATTERN = '/^[A-Za-z0-9_-]{32}$/';
    private const LEGACY_PATTERN = '/^[a-f0-9]{64}$/';

    public function generate(): string
    {
        // 24 cryptographically random bytes become exactly 32 URL-safe characters.
        return rtrim(strtr(base64_encode(random_bytes(24)), '+/', '-_'), '=');
    }

    public function isValid(string $token): bool
    {
        return preg_match(self::CURRENT_PATTERN, $token) === 1 ||
            preg_match(self::LEGACY_PATTERN, $token) === 1;
    }
}
