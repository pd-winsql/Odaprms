<?php

require_once __DIR__ . '/../config/conn.php';
require_once __DIR__ . '/../apps/models/siteSettingsModel.php';

function expectSetting(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$valid = SiteSettingsModel::validatePaymentSettings([
    'deposit_amount' => '425.5',
    'payment_deadline_minutes' => '75',
    'gcash_account_name' => 'Test Clinic',
    'gcash_account_number' => '09123456789',
]);
expectSetting($valid['success'] && $valid['data']['deposit_amount'] === '425.50', 'Valid payment settings are normalized.');

foreach ([
    ['deposit_amount' => '0', 'payment_deadline_minutes' => '75', 'gcash_account_name' => 'Test', 'gcash_account_number' => '0912'],
    ['deposit_amount' => '400.001', 'payment_deadline_minutes' => '75', 'gcash_account_name' => 'Test', 'gcash_account_number' => '0912'],
    ['deposit_amount' => '400', 'payment_deadline_minutes' => '1.5', 'gcash_account_name' => 'Test', 'gcash_account_number' => '0912'],
    ['deposit_amount' => '400', 'payment_deadline_minutes' => '0', 'gcash_account_name' => 'Test', 'gcash_account_number' => '0912'],
    ['deposit_amount' => '400', 'payment_deadline_minutes' => '75', 'gcash_account_name' => '', 'gcash_account_number' => '0912'],
] as $invalid) {
    expectSetting(!SiteSettingsModel::validatePaymentSettings($invalid)['success'], 'Invalid payment settings are rejected.');
}

$validBooking = SiteSettingsModel::validateBookingSettings([
    'minimum_booking_lead_days' => '7',
    'minimum_reschedule_lead_days' => '3',
]);
expectSetting(
    $validBooking['success'] && $validBooking['data']['minimum_booking_lead_days'] === '7'
        && $validBooking['data']['minimum_reschedule_lead_days'] === '3',
    'Compatible booking and reschedule notice values are accepted.'
);
foreach (['-1', '15', '1.5', ''] as $invalidLeadDays) {
    expectSetting(
        !SiteSettingsModel::validateBookingSettings([
            'minimum_booking_lead_days' => $invalidLeadDays,
            'minimum_reschedule_lead_days' => '0',
        ])['success'],
        'Invalid booking notice values are rejected.'
    );
}
expectSetting(
    SiteSettingsModel::validateBookingSettings(['minimum_booking_lead_days' => '0', 'minimum_reschedule_lead_days' => '0'])['success']
        && SiteSettingsModel::validateBookingSettings(['minimum_booking_lead_days' => '14', 'minimum_reschedule_lead_days' => '14'])['success'],
    'Booking notice boundaries from 0 through 14 days are accepted.'
);
expectSetting(
    !SiteSettingsModel::validateBookingSettings(['minimum_booking_lead_days' => '5', 'minimum_reschedule_lead_days' => '6'])['success'],
    'Reschedule notice cannot exceed the booking notice.'
);

$conn = (new Database())->connect();
$model = new SiteSettingsModel($conn);
$conn->beginTransaction();
try {
    expectSetting($model->updateGroup('payment', $valid['data'], 'Settings Test'), 'Validated payment settings can be saved.');
    expectSetting($model->updateGroup('booking', $validBooking['data'], 'Settings Test'), 'Validated booking policy can be saved.');
    $saved = $model->getSettings();
    expectSetting((float) $saved['deposit_amount'] === 425.5 && (int) $saved['payment_deadline_minutes'] === 75, 'Saved payment settings can be read back.');
    expectSetting((int) $saved['minimum_booking_lead_days'] === 7, 'Saved booking policy can be read back.');
    expectSetting((int) $saved['minimum_reschedule_lead_days'] === 3, 'Saved reschedule policy can be read back.');
} finally {
    $conn->rollBack();
}

echo "Site settings test completed.\n";
