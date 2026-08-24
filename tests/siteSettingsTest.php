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

$conn = (new Database())->connect();
$model = new SiteSettingsModel($conn);
$conn->beginTransaction();
try {
    expectSetting($model->updateGroup('payment', $valid['data'], 'Settings Test'), 'Validated payment settings can be saved.');
    $saved = $model->getSettings();
    expectSetting((float) $saved['deposit_amount'] === 425.5 && (int) $saved['payment_deadline_minutes'] === 75, 'Saved payment settings can be read back.');
} finally {
    $conn->rollBack();
}

echo "Site settings test completed.\n";
