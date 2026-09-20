<?php

$root = dirname(__DIR__);
$register = file_get_contents($root . '/apps/views/register.php');
$landing = file_get_contents($root . '/index.php');
$modal = file_get_contents($root . '/apps/views/system-terms.php');
$page = file_get_contents($root . '/apps/views/terms.php');

$checks = [
    'registration has a locked required agreement checkbox' => str_contains($register, 'id="termsAccepted" name="terms_accepted" value="1" required disabled'),
    'checkbox has a real accessible label' => preg_match('/<label[^>]+for="termsAccepted"[^>]*>\s*I agree to the Terms and Conditions\s*<\/label>/', $register) === 1,
    'checkbox explanation and live state are associated' => str_contains($register, 'aria-describedby="termsConsentHint termsConsentStatus"')
        && str_contains($register, 'id="termsConsentStatus" role="status" aria-live="polite" aria-atomic="true"'),
    'registration opens the terms modal' => str_contains($register, 'data-bs-target="#systemTermsModal"'),
    'terms opener exposes dialog name and state' => str_contains($register, 'aria-haspopup="dialog" aria-controls="systemTermsModal" aria-expanded="false"'),
    'agreement is gated by the modal scroll position' => str_contains($register, 'hasReachedTermsEnd()'),
    'registration blocks progression without agreement' => str_contains($register, 'if (!termsAccepted.checked)'),
    'validation errors are associated and announced' => str_contains($register, "termsAccepted.setAttribute('aria-errormessage', 'registerError')")
        && str_contains($register, 'role="alert" aria-live="assertive" aria-atomic="true"'),
    'modal agreement starts disabled' => str_contains($modal, 'id="systemTermsAgreeButton" disabled'),
    'dialog heading receives focus on open' => str_contains($modal, 'id="systemTermsModalLabel" tabindex="-1"')
        && str_contains($register, 'termsHeading.focus()'),
    'dialog traps focus and supports escape' => str_contains($register, "event.key === 'Escape'")
        && str_contains($register, "event.key !== 'Tab'") && str_contains($register, 'last.focus()') && str_contains($register, 'first.focus()'),
    'dialog has a visible named close control' => str_contains($modal, 'aria-label="Close Terms and Conditions dialog">Close terms</button>'),
    'focus returns to the terms opener' => str_contains($register, "termsModal.addEventListener('hidden.bs.modal'")
        && str_contains($register, 'termsOpener.focus()'),
    'scroll changes announce only a state transition' => str_contains($register, 'if (canAgree === termsEndReached)')
        && str_contains($register, 'The Agree to Terms and close button is now available.'),
    'modal and public page share one terms-content partial' => str_contains($modal, "require __DIR__ . '/system-terms-content.php'")
        && str_contains($page, "require __DIR__ . '/system-terms-content.php'"),
    'landing page links to the public terms page' => str_contains($landing, 'href="apps/views/terms.php"'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "Terms consent checks failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "PASS: Public terms links and registration consent gate are wired correctly.\n";
