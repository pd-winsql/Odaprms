<?php
// Run: C:\xampp\php\php.exe tests/cancellationEmailTemplateTest.php
require_once __DIR__ . '/../config/mailer.php';

function cancellationEmailExpect(bool $condition, string $message): void {
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$template = getEmailTemplate('appointment_cancelled');
$variables = [
    '{schedule_summary}' => 'Alcala Branch — October 6, 2026, 7:30 AM–5:00 PM.',
    '{deposit_guidance}' => 'Your verified deposit is marked for refund.',
];
foreach (['intro', 'instruction', 'footer'] as $field) {
    $template[$field] = strtr($template[$field], $variables);
}

$branding = ['brand_name_top' => 'Dr. Aprille', 'brand_name_sub' => 'Clinica Dental'];
$reason = "Patient had an emergency.\nPlease call before rebooking.";
$html = buildEmailHtml('Win Corpuz', $template, $reason, $branding);
$text = buildEmailText('Win Corpuz', $template, $reason);

cancellationEmailExpect(str_contains($html, 'Reason for cancellation') && !str_contains($html, 'Appointment Status'),
    'Cancellation reason is not mislabeled as appointment status.');
cancellationEmailExpect(str_contains($html, 'font-size: 14px; font-weight: 400; letter-spacing: normal;'),
    'Cancellation reason uses readable prose styling.');
cancellationEmailExpect(str_contains($html, 'Patient had an emergency.<br') && str_contains($html, 'Please call before rebooking.'),
    'Multi-line staff reasons remain readable.');
cancellationEmailExpect(str_contains($html, 'patient account') && str_contains($html, 'marked for refund'),
    'HTML email explains rebooking and refundable-deposit next steps.');
cancellationEmailExpect(str_contains($text, 'Cancelled schedule:') && str_contains($text, 'Reason for cancellation:')
    && str_contains($text, 'marked for refund'),
    'Plain-text email includes schedule, reason, and deposit guidance.');

$unsafe = buildEmailHtml('Patient', $template, '<script>alert(1)</script>', $branding);
cancellationEmailExpect(!str_contains($unsafe, '<script>') && str_contains($unsafe, '&lt;script&gt;'),
    'Staff-entered reason remains escaped in HTML.');

$withoutDeposit = getEmailTemplate('appointment_cancelled');
$withoutDeposit['instruction'] = strtr($withoutDeposit['instruction'], [
    '{schedule_summary}' => $variables['{schedule_summary}'],
    '{deposit_guidance}' => '',
]);
$withoutDepositText = buildEmailText('Win Corpuz', $withoutDeposit, $reason);
cancellationEmailExpect(!str_contains($withoutDepositText, '{deposit_guidance}')
    && !str_contains($withoutDepositText, 'marked for refund'),
    'Unpaid cancellations do not claim a refundable deposit exists.');

echo "Cancellation email template checks passed.\n";
