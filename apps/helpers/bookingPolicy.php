<?php

require_once __DIR__ . '/../models/siteSettingsModel.php';

final class BookingPolicy
{
    public static function minimumLeadDays(?PDO $conn): int
    {
        if (!$conn) {
            return SiteSettingsModel::DEFAULT_MINIMUM_BOOKING_LEAD_DAYS;
        }

        try {
            $settings = (new SiteSettingsModel($conn))->getSettings();
            return self::normalizeLeadDays(
                $settings['minimum_booking_lead_days'] ?? SiteSettingsModel::DEFAULT_MINIMUM_BOOKING_LEAD_DAYS
            );
        } catch (Throwable $e) {
            error_log('Booking policy settings error: ' . $e->getMessage());
            return SiteSettingsModel::DEFAULT_MINIMUM_BOOKING_LEAD_DAYS;
        }
    }

    public static function minimumRescheduleLeadDays(?PDO $conn): int
    {
        if (!$conn) {
            return SiteSettingsModel::DEFAULT_MINIMUM_RESCHEDULE_LEAD_DAYS;
        }

        try {
            $settings = (new SiteSettingsModel($conn))->getSettings();
            $bookingLeadDays = self::normalizeLeadDays(
                $settings['minimum_booking_lead_days'] ?? SiteSettingsModel::DEFAULT_MINIMUM_BOOKING_LEAD_DAYS
            );
            return self::normalizeRescheduleLeadDays(
                $settings['minimum_reschedule_lead_days'] ?? SiteSettingsModel::DEFAULT_MINIMUM_RESCHEDULE_LEAD_DAYS,
                $bookingLeadDays
            );
        } catch (Throwable $e) {
            error_log('Reschedule policy settings error: ' . $e->getMessage());
            return SiteSettingsModel::DEFAULT_MINIMUM_RESCHEDULE_LEAD_DAYS;
        }
    }

    public static function normalizeRescheduleLeadDays(mixed $value, int $bookingLeadDays): int
    {
        $bookingLeadDays = self::normalizeLeadDays($bookingLeadDays);
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            $value = SiteSettingsModel::DEFAULT_MINIMUM_RESCHEDULE_LEAD_DAYS;
        }
        return max(0, min($bookingLeadDays, (int) $value));
    }

    public static function normalizeLeadDays(mixed $value): int
    {
        if (filter_var($value, FILTER_VALIDATE_INT) === false) {
            return SiteSettingsModel::DEFAULT_MINIMUM_BOOKING_LEAD_DAYS;
        }

        return max(
            SiteSettingsModel::MINIMUM_BOOKING_LEAD_DAYS_LIMIT,
            min(SiteSettingsModel::MAXIMUM_BOOKING_LEAD_DAYS_LIMIT, (int) $value)
        );
    }

    public static function earliestBookableDate(int $leadDays, ?DateTimeImmutable $today = null): string
    {
        $today ??= new DateTimeImmutable('today');
        $leadDays = self::normalizeLeadDays($leadDays);
        return $today->add(new DateInterval("P{$leadDays}D"))->format('Y-m-d');
    }

    public static function assessDate(string $scheduleDate, int $leadDays, ?DateTimeImmutable $today = null): array
    {
        $today ??= new DateTimeImmutable('today');
        $leadDays = self::normalizeLeadDays($leadDays);
        $date = DateTimeImmutable::createFromFormat('!Y-m-d', trim($scheduleDate));
        $dateErrors = DateTimeImmutable::getLastErrors();
        $hasDateErrors = is_array($dateErrors)
            && (($dateErrors['warning_count'] ?? 0) > 0 || ($dateErrors['error_count'] ?? 0) > 0);
        $earliestDate = self::earliestBookableDate($leadDays, $today);

        if (!$date || $hasDateErrors || $date->format('Y-m-d') !== trim($scheduleDate)) {
            return [
                'valid' => false,
                'eligible' => false,
                'earliest_date' => $earliestDate,
                'message' => 'Please choose a valid appointment date.',
            ];
        }

        $eligible = $date->format('Y-m-d') >= $earliestDate;
        return [
            'valid' => true,
            'eligible' => $eligible,
            'earliest_date' => $earliestDate,
            'message' => $eligible ? '' : self::restrictionMessage($leadDays, $earliestDate),
        ];
    }

    public static function restrictionMessage(int $leadDays, string $earliestDate): string
    {
        $leadDays = self::normalizeLeadDays($leadDays);
        $dateLabel = date('F j, Y', strtotime($earliestDate));
        if ($leadDays === 0) {
            return "Please choose an appointment on {$dateLabel} or later.";
        }

        $unit = $leadDays === 1 ? 'day' : 'days';
        return "Appointments must be booked at least {$leadDays} calendar {$unit} in advance. Please choose {$dateLabel} or later.";
    }
}
