<?php
declare(strict_types=1);

if (PHP_SAPI !== 'cli') {
    http_response_code(403);
    exit;
}

require_once __DIR__ . '/../../config/conn.php';
require_once __DIR__ . '/../../apps/models/analyticsModel.php';
require_once __DIR__ . '/../../apps/models/billingModel.php';
require_once __DIR__ . '/../../apps/models/reviewModel.php';
require_once __DIR__ . '/../../apps/models/logbookModel.php';

$conn = (new Database())->connect();
if (!$conn) {
    fwrite(STDERR, "Database unavailable.\n");
    exit(1);
}

function countQuery(PDO $conn, string $sql): int
{
    return (int) $conn->query($sql)->fetchColumn();
}

function expectCount(string $label, int $actual, int $expected): void
{
    if ($actual !== $expected) {
        throw new RuntimeException("$label: expected $expected, found $actual.");
    }
}

try {
    expectCount('patients', countQuery($conn, 'SELECT COUNT(*) FROM patients'), 20);
    expectCount('historical appointments', countQuery($conn, "SELECT COUNT(*) FROM appointments WHERE date <= '2026-09-27'"), 35);
    expectCount('historical schedules', countQuery($conn, "SELECT COUNT(*) FROM schedules WHERE sched_date <= '2026-09-27'"), 22);
    expectCount('historical checkins', countQuery($conn, "SELECT COUNT(*) FROM appointment_checkins c JOIN appointments a ON a.appointment_id = c.appointment_id WHERE a.date <= '2026-09-27'"), 26);
    expectCount('historical deposits', countQuery($conn, "SELECT COUNT(*) FROM appointment_deposits d JOIN appointments a ON a.appointment_id = d.appointment_id WHERE a.date <= '2026-09-27'"), 28);
    expectCount('billings', countQuery($conn, 'SELECT COUNT(*) FROM appointment_billings'), 26);
    expectCount('reviews', countQuery($conn, 'SELECT COUNT(*) FROM appointment_reviews'), 16);
    expectCount('Admin users', countQuery($conn, "SELECT COUNT(*) FROM users WHERE user_role = 'Admin'"), 2);
    expectCount('Dental Assistant users', countQuery($conn, "SELECT COUNT(*) FROM users WHERE user_role = 'Dental Assistant'"), 3);
    expectCount('clinics', countQuery($conn, 'SELECT COUNT(*) FROM clinics'), 2);
    expectCount('services', countQuery($conn, 'SELECT COUNT(*) FROM services'), 13);
    expectCount('invalid or duplicate deposit references', countQuery($conn, 'SELECT COUNT(*) - COUNT(DISTINCT gcash_reference) FROM appointment_deposits'), 0);
    expectCount('deposit amount other than 150', countQuery($conn, 'SELECT COUNT(*) FROM appointment_deposits WHERE amount <> 150 OR receipt_amount <> 150'), 0);
    expectCount('incomplete patient profiles', countQuery($conn, "SELECT COUNT(*) FROM patients WHERE profile_status <> 'Complete' OR profile_completed_at IS NULL"), 0);
    expectCount('placeholder patient names', countQuery($conn, "SELECT COUNT(*) FROM patients WHERE firstname REGEXP 'test|pogi' OR lastname REGEXP 'test|pogi'"), 0);
    expectCount('invalid patient emails', countQuery($conn, "SELECT COUNT(*) FROM patients WHERE email <> CONCAT(LOWER(firstname), LOWER(lastname), '.av@gmail.com')"), 0);
    expectCount('review of non-completed visit', countQuery($conn, "SELECT COUNT(*) FROM appointment_reviews r JOIN appointments a ON a.appointment_id = r.appointment_id WHERE a.status <> 'Completed'"), 0);
    expectCount('billing of non-completed visit', countQuery($conn, "SELECT COUNT(*) FROM appointment_billings b JOIN appointments a ON a.appointment_id = b.appointment_id WHERE a.status <> 'Completed'"), 0);
    expectCount('checkin of non-completed visit', countQuery($conn, "SELECT COUNT(*) FROM appointment_checkins c JOIN appointments a ON a.appointment_id = c.appointment_id WHERE a.status <> 'Completed'"), 0);
    expectCount('billing totals mismatch', countQuery($conn, 'SELECT COUNT(*) FROM appointment_billings b LEFT JOIN (SELECT billing_id, SUM(quantity * unit_price) AS item_total FROM appointment_billing_items GROUP BY billing_id) i ON i.billing_id = b.billing_id WHERE ABS(b.actual_service_amount - COALESCE(i.item_total, 0)) > 0.001 OR ABS(b.actual_service_amount - b.deposit_applied - b.remaining_balance) > 0.001 OR ABS(b.cash_received - b.remaining_balance) > 0.001'), 0);
    expectCount('queued email notifications', countQuery($conn, 'SELECT COUNT(*) FROM appointment_email_notifications'), 0);

    $passwords = $conn->query("SELECT password FROM users WHERE user_role = 'Patient'")->fetchAll(PDO::FETCH_COLUMN);
    expectCount('patient accounts', count($passwords), 20);
    foreach ($passwords as $hash) {
        if (!password_verify('password1', $hash)) {
            throw new RuntimeException('A patient password is invalid.');
        }
    }

    $analytics = new AnalyticsModel($conn);
    $analyticsData = $analytics->getDashboardData(AnalyticsModel::normalizeFilters([
        'date_from' => '2026-08-01', 'date_to' => '2026-09-27',
    ]));
    $billing = new BillingModel($conn);
    $billingData = $billing->getBillingInsights(BillingModel::normalizeInsightFilters([
        'period' => 'custom', 'date_from' => '2026-08-09', 'date_to' => '2026-09-27',
    ]));
    $ratings = (new ReviewModel($conn))->getAdminSummary();
    expectCount('ratings dashboard total', $ratings['total_reviews'], 16);
    $logbook = new LogbookModel($conn);
    $logbookDates = $logbook->getRecordDates();
    if (count($logbookDates) < 20 || count($logbook->getForDate('2026-08-09')) < 1) {
        throw new RuntimeException('Historical logbook entries are unavailable.');
    }

    echo json_encode([
        'verified' => true,
        'analytics_kpis' => $analyticsData['kpis'],
        'analytics_status_distribution' => $analyticsData['status_distribution'],
        'billing_summary' => $billingData['summary'],
        'ratings_summary' => $ratings,
        'historical_logbook_dates' => count($logbookDates),
    ], JSON_PRETTY_PRINT | JSON_THROW_ON_ERROR), PHP_EOL;
} catch (Throwable $error) {
    fwrite(STDERR, $error->getMessage() . PHP_EOL);
    exit(1);
}
