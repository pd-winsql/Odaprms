<?php

final class PatientProfilePolicy
{
    public const AGE_OF_MAJORITY = 18;

    private const CONSENT_LABELS = [
        'myself' => 'Myself',
        'spouse' => 'Spouse',
        'son' => 'Son',
        'daughter' => 'Daughter',
        'others' => 'Others',
    ];

    private const MINOR_CONSENT_VALUES = ['son', 'daughter', 'others'];

    public static function consentOptions(): array
    {
        return self::CONSENT_LABELS;
    }

    public static function normalizeConsentFor(mixed $value): string
    {
        return strtolower(trim((string) $value));
    }

    public static function isAllowedConsentFor(mixed $value): bool
    {
        return array_key_exists(self::normalizeConsentFor($value), self::CONSENT_LABELS);
    }

    public static function consentLabel(mixed $value, string $fallback = '—'): string
    {
        return self::CONSENT_LABELS[self::normalizeConsentFor($value)] ?? $fallback;
    }

    public static function ageFromBirthdate(?string $birthdate, ?DateTimeImmutable $today = null): ?int
    {
        $birthdate = trim((string) $birthdate);
        if ($birthdate === '') return null;

        $birth = DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate);
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);
        $today ??= new DateTimeImmutable('today');

        if (!$birth || $hasDateErrors || $birth->format('Y-m-d') !== $birthdate || $birth > $today) {
            return null;
        }

        return $birth->diff($today)->y;
    }

    public static function isMinor(?string $birthdate, ?DateTimeImmutable $today = null): bool
    {
        $age = self::ageFromBirthdate($birthdate, $today);
        return $age !== null && $age < self::AGE_OF_MAJORITY;
    }

    public static function normalizeContact(mixed $value): string
    {
        return preg_replace('/\D+/', '', trim((string) $value)) ?? '';
    }

    public static function isValidGuardianContact(mixed $value): bool
    {
        return preg_match('/^\d{7,15}$/', self::normalizeContact($value)) === 1;
    }

    public static function minorRequirementErrors(array $data): array
    {
        if (!self::isMinor($data['birthdate'] ?? null)) return [];

        $errors = [];
        if (trim((string) ($data['guardian_name'] ?? '')) === '') {
            $errors[] = 'Parent or guardian name is required for patients under 18.';
        }
        if (!self::isValidGuardianContact($data['guardian_contact'] ?? '')) {
            $errors[] = 'Enter a valid parent or guardian contact number containing 7 to 15 digits.';
        }
        if (trim((string) ($data['consent_name'] ?? '')) === '') {
            $errors[] = 'The parent or guardian providing consent must be named.';
        }

        $consentFor = self::normalizeConsentFor($data['consent_for'] ?? '');
        if (!in_array($consentFor, self::MINOR_CONSENT_VALUES, true)) {
            $errors[] = 'For a minor, consent must apply to the representative’s son, daughter, or other dependent.';
        }

        return $errors;
    }
}
