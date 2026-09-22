<?php

$root = dirname(__DIR__);
$register = file_get_contents($root . '/apps/views/register.php');
$landing = file_get_contents($root . '/index.php');
$modal = file_get_contents($root . '/apps/views/system-terms.php');
$page = file_get_contents($root . '/apps/views/terms.php');

$checks = [
    'registration has an unchecked required agreement checkbox' => str_contains($register, 'id="termsAccepted" name="terms_accepted" value="1" required')
        && !str_contains($register, 'name="terms_accepted" value="1" required disabled'),
    'checkbox sentence names the agreement and links the terms' => str_contains($register, 'aria-labelledby="termsConsentLabel openSystemTerms"')
        && str_contains($register, 'for="termsAccepted">I agree to the</label>')
        && str_contains($register, '>Terms and Conditions</button>'),
    'checkbox has an associated inline error' => str_contains($register, 'aria-describedby="termsConsentError"')
        && str_contains($register, 'id="termsConsentError" role="alert" hidden'),
    'registration opens the terms modal' => str_contains($register, 'data-bs-target="#systemTermsModal"'),
    'terms opener exposes dialog name and state' => str_contains($register, 'aria-haspopup="dialog" aria-controls="systemTermsModal" aria-expanded="false"'),
    'registration blocks progression without agreement' => str_contains($register, 'if (!termsAccepted.checked)'),
    'validation errors are associated and announced' => str_contains($register, "termsAccepted.setAttribute('aria-invalid', 'true')")
        && str_contains($register, 'id="termsConsentError" role="alert" hidden'),
    'modal is for reading, not gated agreement' => str_contains($modal, 'id="systemTermsCloseButton" data-bs-dismiss="modal"')
        && !str_contains($modal, 'id="systemTermsAgreeButton"'),
    'dialog heading receives focus on open' => str_contains($modal, 'id="systemTermsModalLabel" tabindex="-1"')
        && str_contains($register, 'termsHeading.focus()'),
    'dialog traps focus and supports escape' => str_contains($register, "event.key === 'Escape'")
        && str_contains($register, "event.key !== 'Tab'") && str_contains($register, 'last.focus()') && str_contains($register, 'first.focus()'),
    'dialog has a visible named close control' => str_contains($modal, 'aria-label="Close Terms and Conditions dialog"')
        && str_contains($modal, 'aria-hidden="true">&times;</span>'),
    'focus returns to the terms opener' => str_contains($register, "termsModal.addEventListener('hidden.bs.modal'")
        && str_contains($register, 'termsOpener.focus()'),
    'agreement is not gated by scrolling' => !str_contains($register, 'hasReachedTermsEnd()')
        && !str_contains($register, 'termsScrollRegion.addEventListener'),
    'modal and public page share one terms-content partial' => str_contains($modal, "require __DIR__ . '/system-terms-content.php'")
        && str_contains($page, "require __DIR__ . '/system-terms-content.php'"),
    'landing page links to the public terms page' => str_contains($landing, 'href="apps/views/terms.php"'),
];

$failed = array_keys(array_filter($checks, static fn(bool $passed): bool => !$passed));
if ($failed !== []) {
    fwrite(STDERR, "Terms consent checks failed:\n- " . implode("\n- ", $failed) . "\n");
    exit(1);
}

echo "PASS: Public terms links and registration consent checkbox are wired correctly.\n";
