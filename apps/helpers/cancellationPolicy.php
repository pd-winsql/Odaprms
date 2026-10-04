<?php
require_once __DIR__ . '/../models/siteSettingsModel.php';
final class CancellationPolicy {
    public static function days(PDO $conn): int {
        $settings = (new SiteSettingsModel($conn))->getSettings();
        return max(0, min(30, (int)($settings['minimum_cancellation_notice_days'] ?? 2)));
    }
    public static function eligibility(array $appointment, int $days, ?DateTimeImmutable $now = null): array {
        $zone = new DateTimeZone('Asia/Manila');
        $now ??= new DateTimeImmutable('now', $zone);
        $status = $appointment['status'] ?? '';
        $allowed = in_array($status, ['Pending Review','Awaiting Deposit','Payment Under Review','Confirmed'], true)
            && empty($appointment['has_checkin']);
        $deadline = null;
        if ($status === 'Confirmed') {
            $start = new DateTimeImmutable($appointment['date'].' '.$appointment['start_time'], $zone);
            $deadline = $start->modify('-'.max(0,$days).' days');
            $allowed = $allowed && $now < $deadline;
        }
        return ['allowed'=>$allowed, 'deadline'=>$deadline,
            'message'=>$allowed ? '' : 'Online cancellation is closed. Contact the clinic.'];
    }
}
