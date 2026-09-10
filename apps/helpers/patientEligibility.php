<?php

require_once __DIR__ . '/../models/siteSettingsModel.php';

final class PatientEligibility
{
    public const DEFAULT_MINIMUM_AGE = SiteSettingsModel::DEFAULT_MINIMUM_PATIENT_AGE;
    public const MINIMUM_CONFIGURABLE_AGE = SiteSettingsModel::MINIMUM_PATIENT_AGE_LIMIT;
    public const MAXIMUM_CONFIGURABLE_AGE = SiteSettingsModel::MAXIMUM_PATIENT_AGE_LIMIT;

    public static function minimumAge(?PDO $conn): int
    {
        if (!$conn) {
            return self::DEFAULT_MINIMUM_AGE;
        }

        try {
            $settings = (new SiteSettingsModel($conn))->getSettings();
            $value = $settings['minimum_patient_age_years'] ?? self::DEFAULT_MINIMUM_AGE;
            return self::normalizeMinimumAge($value);
        } catch (Throwable $e) {
            error_log('Patient eligibility settings error: ' . $e->getMessage());
            return self::DEFAULT_MINIMUM_AGE;
        }
    }

    public static function normalizeMinimumAge(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return self::DEFAULT_MINIMUM_AGE;
        }

        return max(
            self::MINIMUM_CONFIGURABLE_AGE,
            min(self::MAXIMUM_CONFIGURABLE_AGE, (int) $value)
        );
    }

    public static function latestEligibleBirthdate(int $minimumAge, ?DateTimeImmutable $today = null): string
    {
        $today ??= new DateTimeImmutable('today');
        $minimumAge = self::normalizeMinimumAge($minimumAge);
        return $today->sub(new DateInterval("P{$minimumAge}Y"))->format('Y-m-d');
    }

    public static function assess(?string $birthdate, int $minimumAge, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $minimumAge = self::normalizeMinimumAge($minimumAge);
        $birthdate = trim((string) $birthdate);
        $birth = $birthdate !== '' ? DateTimeImmutable::createFromFormat('!Y-m-d', $birthdate) : false;
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);

        if (!$birth || $hasDateErrors || $birth->format('Y-m-d') !== $birthdate || $birth > $today) {
            return [
                'valid' => false,
                'eligible' => false,
                'message' => 'Please enter a valid birthdate.',
                'eligible_on' => null,
            ];
        }

        $eligibleOn = $birth->add(new DateInterval("P{$minimumAge}Y"));
        $eligible = $eligibleOn <= $today;

        return [
            'valid' => true,
            'eligible' => $eligible,
            'message' => $eligible ? '' : self::ineligibleMessage($minimumAge),
            'eligible_on' => $eligibleOn->format('Y-m-d'),
        ];
    }

    public static function requirementMessage(int $minimumAge): string
    {
        $minimumAge = self::normalizeMinimumAge($minimumAge);
        if ($minimumAge === 0) {
            return 'Patients of any age may register and book an appointment.';
        }

        $unit = $minimumAge === 1 ? 'year' : 'years';
        return "Patients must be at least {$minimumAge} {$unit} old to register and book an appointment.";
    }

    public static function ineligibleMessage(int $minimumAge): string
    {
        $minimumAge = self::normalizeMinimumAge($minimumAge);
        $unit = $minimumAge === 1 ? 'year' : 'years';
        return "Patients must be at least {$minimumAge} {$unit} old to register or book an appointment.";
    }
}
