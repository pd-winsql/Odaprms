<?php
require_once __DIR__ . '/../apps/support/IdentityInput.php';
require_once __DIR__ . '/../apps/support/PatientProfilePolicy.php';
require_once __DIR__ . '/../apps/models/siteSettingsModel.php';

function expectIdentity(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: $message\n";
}

foreach (['Julie Ann Mae', 'Dela Cruz', 'Peña', 'José', "Jose\u{0301}", '李 明'] as $name) {
    expectIdentity(IdentityInput::isName($name), "Accepts a letter-only name: $name");
}
foreach (['Patient123', '123', 'Win@Corpuz', '   ', "\u{0301}Ana", 'Ana-Maria'] as $name) {
    expectIdentity(!IdentityInput::isName($name), 'Rejects digits, symbols, and missing letters in names.');
}
foreach (['firstname', 'middlename', 'lastname', 'guardian_name', 'physician_name', 'consent_name', 'previous_dentist'] as $field) {
    expectIdentity(isset(IdentityInput::errors([$field => 'Ana123'])[$field]), "$field rejects digits on the server.");
}
foreach (['phone_number', 'guardian_contact', 'office_contact', 'physician_contact', 'gcash_account_number'] as $field) {
    expectIdentity(IdentityInput::errors([$field => '09123456789']) === [], "$field accepts exactly 11 digits.");
    foreach (['0912345678', '091234567890', '0912345678a', '+639123456789', '09123 456789'] as $invalid) {
        expectIdentity(isset(IdentityInput::errors([$field => $invalid])[$field]), "$field rejects an invalid completed number.");
    }
}
expectIdentity(IdentityInput::errors(['middlename' => '', 'guardian_contact' => '', 'physician_contact' => '']) === [], 'Optional fields can remain blank.');
expectIdentity(IdentityInput::errors(['phone_number' => '0912', 'guardian_contact' => '09'], true) === [], 'Drafts retain unfinished numeric contacts.');
expectIdentity(IdentityInput::errors(['phone_number' => '09abc'], true) !== [], 'Drafts reject letters in contacts.');
expectIdentity(IdentityInput::errors(['firstname' => str_repeat('a', 101)]) !== [], 'Names respect the database length limit.');
expectIdentity(!PatientProfilePolicy::isValidGuardianContact('09123456789abc'), 'Guardian validation cannot strip letters to silently accept an invalid number.');
expectIdentity(!PatientProfilePolicy::isValidGuardianContact('0912345'), 'Guardian policy requires 11 digits.');
expectIdentity(IdentityInput::errors(['contact_phone' => "09123456789\n09987654321"]) === [], 'Clinic phone lists accept one complete number per line.');
expectIdentity(IdentityInput::errors(['contact_phone' => "09123456789\n09987abc"]) !== [], 'Clinic phone lists reject invalid entries.');
expectIdentity(!SiteSettingsModel::validatePaymentSettings([
    'deposit_amount' => '200', 'payment_deadline_minutes' => '60',
    'gcash_account_name' => 'Test Clinic', 'gcash_account_number' => '0912abc',
])['success'], 'GCash settings reject invalid numbers before saving.');
