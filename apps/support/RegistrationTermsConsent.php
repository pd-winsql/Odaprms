<?php

final class RegistrationTermsConsent
{
    public const VERSION = '2026-08-02';
    public const SESSION_KEY = 'registration_terms_challenge';
    public const MAX_AGE_SECONDS = 7200;

    public static function issue(array &$session, ?int $now = null): string
    {
        $token = bin2hex(random_bytes(32));
        $session[self::SESSION_KEY] = [
            'token_hash' => hash('sha256', $token),
            'version' => self::VERSION,
            'issued_at' => $now ?? time(),
        ];
        return $token;
    }

    public static function validate(array $session, array $submission, ?int $now = null): ?array
    {
        $challenge = $session[self::SESSION_KEY] ?? null;
        $token = $submission['terms_consent_token'] ?? null;
        $currentTime = $now ?? time();

        if (!is_array($challenge) || !is_string($token) || $token === ''
            || ($submission['terms_accepted'] ?? null) !== '1') {
            return null;
        }
        if (($challenge['version'] ?? null) !== self::VERSION
            || !isset($challenge['issued_at'], $challenge['token_hash'])
            || !is_int($challenge['issued_at'])
            || $challenge['issued_at'] > $currentTime
            || $currentTime - $challenge['issued_at'] > self::MAX_AGE_SECONDS
            || !is_string($challenge['token_hash'])
            || !hash_equals($challenge['token_hash'], hash('sha256', $token))) {
            return null;
        }

        return [
            'version' => self::VERSION,
            'accepted_at' => gmdate('c', $currentTime),
        ];
    }

    public static function isRecordedConsentValid($consent): bool
    {
        if (!is_array($consent) || ($consent['version'] ?? null) !== self::VERSION) {
            return false;
        }
        $acceptedAt = $consent['accepted_at'] ?? null;
        return is_string($acceptedAt) && $acceptedAt !== '' && strtotime($acceptedAt) !== false;
    }
}
