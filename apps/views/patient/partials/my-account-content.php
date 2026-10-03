<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/accountEmailChangeModel.php';
require_once __DIR__ . '/../../../helpers/csrf.php';

$conn = (new Database())->connect();
$account = (new Patient($conn))->getPatientByUserId((int) $_SESSION['user_id']);
if (!$account) {
    echo '<div class="vd-empty-state">Account details are unavailable.</div>';
    exit;
}
$pendingEmail = (new AccountEmailChange($conn))->pending((int) $_SESSION['user_id']);
$escape = static fn($value) => htmlspecialchars((string) ($value ?? ''), ENT_QUOTES, 'UTF-8');
?>
<section class="vd-my-account" aria-labelledby="myAccountTitle"
    data-controller="apps/controllers/patientAccountController.php"
    data-csrf="<?= $escape(get_csrf_token()) ?>"
    data-open-password="<?= !empty($openAccountPassword) ? '1' : '0' ?>">
    <header class="vd-password-heading">
        <span class="vd-welcome-greet">Account</span>
        <h1 class="vd-welcome-name" id="myAccountTitle">My Account</h1>
        <p>Manage your account details.</p>
    </header>

    <div class="vd-dash-card vd-my-account-panel">
        <div class="vd-my-account-panel-body">
            <h2 class="vd-account-section-title">Basic details</h2>
            <form id="myAccountForm" class="vd-my-account-form" novalidate>
                <div class="vd-my-account-grid">
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountFirst">First name</label>
                        <input class="vd-input form-control" id="myAccountFirst" name="firstname" pattern=" *\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)* *" title="Use letters and spaces only." maxlength="100" autocomplete="given-name" value="<?= $escape($account['firstname']) ?>" required>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountMiddle">Middle name <span class="vd-label-optional">Optional</span></label>
                        <input class="vd-input form-control" id="myAccountMiddle" name="middlename" pattern=" *\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)* *" title="Use letters and spaces only." maxlength="100" autocomplete="additional-name" value="<?= $escape($account['middlename']) ?>">
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountLast">Last name</label>
                        <input class="vd-input form-control" id="myAccountLast" name="lastname" pattern=" *\p{L}[\p{L}\p{M}]*(?: +\p{L}[\p{L}\p{M}]*)* *" title="Use letters and spaces only." maxlength="100" autocomplete="family-name" value="<?= $escape($account['lastname']) ?>" required>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountPhone">Phone number</label>
                        <input class="vd-input form-control" id="myAccountPhone" name="phone_number" type="tel" inputmode="numeric" pattern="[0-9]{11}" maxlength="11" autocomplete="tel" value="<?= $escape($account['phone_number']) ?>" required>
                    </div>
                </div>
                <p id="myAccountMessage" class="vd-my-account-message" role="status" aria-live="polite"></p>
                <div class="vd-my-account-actions">
                    <button class="btn vd-btn-gold" id="myAccountSave" type="submit" disabled>Save changes</button>
                    <span id="myAccountChangeState" aria-live="polite">No unsaved changes</span>
                </div>
            </form>
        </div>
    </div>

    <div class="vd-dash-card vd-my-account-panel">
        <div class="vd-my-account-panel-body">
            <h2 class="vd-account-section-title">Email address</h2>
            <p class="vd-account-help">Current email: <strong id="myAccountCurrentEmail"><?= $escape($account['email']) ?></strong></p>
            <form id="myAccountEmailForm" class="vd-my-account-form" novalidate>
                <div class="vd-my-account-grid">
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountEmail">New email address</label>
                        <input class="vd-input form-control" id="myAccountEmail" name="email" type="email" maxlength="255" autocomplete="email" value="<?= $escape($pendingEmail['new_email'] ?? '') ?>" required>
                        <p class="vd-field-feedback" data-error-for="email" aria-live="polite"></p>
                    </div>
                    <div class="vd-my-account-field">
                        <label class="vd-label form-label" for="myAccountEmailPassword">Current password</label>
                        <div class="vd-auth-input-wrap">
                            <input class="vd-input vd-auth-input form-control" id="myAccountEmailPassword" name="current_password" type="password" autocomplete="current-password" required>
                            <button type="button" class="vd-pw-toggle" data-toggle-my-account-password="myAccountEmailPassword" aria-label="Show current password">
                                <i class="ti ti-eye" aria-hidden="true"></i><span hidden>Show password</span>
                            </button>
                        </div>
                        <p class="vd-field-feedback" data-error-for="current_password" aria-live="polite"></p>
                    </div>
                </div>
                <p class="vd-account-help">A verification code will be sent to your new email.</p>
                <div class="vd-my-account-actions">
                    <button class="btn vd-btn-gold" id="myAccountSendCode" type="submit" disabled>Send verification code</button>
                </div>
                <div id="myAccountVerification" class="vd-account-verification" <?= $pendingEmail ? '' : 'hidden' ?> data-pending-email="<?= $escape($pendingEmail['new_email'] ?? '') ?>">
                    <label class="vd-label form-label" for="myAccountCode">Verification code</label>
                    <input class="vd-input form-control" id="myAccountCode" name="code" type="text" inputmode="numeric" autocomplete="one-time-code" pattern="[0-9]{6}" maxlength="6">
                    <p class="vd-field-feedback" data-error-for="code" aria-live="polite"></p>
                    <button class="btn vd-btn-gold" id="myAccountVerify" type="button" disabled>Verify email</button>
                </div>
                <p id="myAccountEmailMessage" class="vd-my-account-message" role="status" aria-live="polite"></p>
            </form>
        </div>
    </div>

    <details class="vd-account-password" id="myAccountPasswordSection">
        <summary><span>Change password</span><i class="ti ti-chevron-down" aria-hidden="true"></i></summary>
        <?php $embeddedAccountPassword = true; require __DIR__ . '/../../shared/change-password-content.php'; ?>
    </details>
</section>
<script><?php readfile(__DIR__ . '/../../../../public/js/my-account.js'); ?></script>
