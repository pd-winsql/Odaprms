<?php

require_once __DIR__ . '/../apps/support/RegistrationTermsConsent.php';

function expectTermsConsent(bool $condition, string $message): void
{
    if (!$condition) throw new RuntimeException($message);
    echo "PASS: {$message}\n";
}

$session = [];
$issuedAt = 1_800_000_000;
$token = RegistrationTermsConsent::issue($session, $issuedAt);

expectTermsConsent(strlen($token) === 64 && !isset($session[RegistrationTermsConsent::SESSION_KEY]['token']), 'The browser receives a random token while the session stores only its hash.');
expectTermsConsent(RegistrationTermsConsent::validate($session, [], $issuedAt + 1) === null, 'Consent cannot be forged by omitting the acceptance and proof.');
expectTermsConsent(RegistrationTermsConsent::validate($session, ['terms_accepted' => '1'], $issuedAt + 1) === null, 'Acceptance without the session proof is rejected.');
expectTermsConsent(RegistrationTermsConsent::validate($session, ['terms_accepted' => '1', 'terms_consent_token' => str_repeat('0', 64)], $issuedAt + 1) === null, 'A forged proof is rejected.');
expectTermsConsent(RegistrationTermsConsent::validate($session, ['terms_accepted' => '0', 'terms_consent_token' => $token], $issuedAt + 1) === null, 'A valid proof without affirmative consent is rejected.');
expectTermsConsent(RegistrationTermsConsent::validate($session, ['terms_accepted' => '1', 'terms_consent_token' => $token], $issuedAt + RegistrationTermsConsent::MAX_AGE_SECONDS + 1) === null, 'An expired consent challenge is rejected.');

$consent = RegistrationTermsConsent::validate($session, ['terms_accepted' => '1', 'terms_consent_token' => $token], $issuedAt + 30);
expectTermsConsent(RegistrationTermsConsent::isRecordedConsentValid($consent), 'Valid consent records the current terms version and an acceptance timestamp.');
expectTermsConsent(!RegistrationTermsConsent::isRecordedConsentValid(['version' => 'obsolete', 'accepted_at' => $consent['accepted_at']]), 'A stale terms version cannot complete registration.');

$controller = file_get_contents(__DIR__ . '/../apps/controllers/userController.php');
expectTermsConsent(str_contains($controller, 'RegistrationTermsConsent::validate($_SESSION, $_POST)'), 'The registration endpoint validates server-side consent proof.');
expectTermsConsent(str_contains($controller, "'registration_terms_accepted'"), 'Verified account creation records consent in the audit trail.');

echo "Registration terms server-consent test completed.\n";
