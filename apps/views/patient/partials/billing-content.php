<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Patient') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/depositModel.php';
require_once __DIR__ . '/../../../models/siteSettingsModel.php';

$db = new Database();
$conn = $db->connect();
$patient = (new Patient($conn))->getPatientByUserId($_SESSION['user_id']);
$depositModel = new DepositModel($conn);
$depositModel->expireUnpaidAppointments();
$deposits = $patient ? $depositModel->getPatientDeposits($patient['patient_id']) : [];
$settings = (new SiteSettingsModel($conn))->getSettings();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];

function depositStatusClass($status) {
    $key = strtolower(str_replace(' ', '-', $status));
    return 'vd-status vd-status-' . $key;
}
?>

<div class="d-flex flex-column gap-4">
    <div>
        <div class="vd-welcome-greet">BILLING</div>
        <div class="vd-welcome-name">Appointment deposits</div>
        <p class="text-muted small mb-0 mt-2">A fixed ₱<?= number_format((float) ($settings['deposit_amount'] ?? 400), 2) ?> GCash deposit is required to confirm each new booking.</p>
    </div>

    <?php if (empty($deposits)): ?>
        <div class="vd-dash-card"><div class="vd-empty-state">You have no appointment deposits.</div></div>
    <?php else: ?>
        <?php foreach ($deposits as $deposit): ?>
        <?php
            $canSubmit = in_array($deposit['deposit_status'], ['Awaiting Submission', 'Rejected'], true)
                && $deposit['appointment_status'] === 'Awaiting Payment';
            $deadline = $deposit['resubmission_deadline_at'] ?: $deposit['payment_deadline_at'];
        ?>
        <div class="vd-dash-card vd-payment-card" data-deposit-card="<?= (int) $deposit['deposit_id'] ?>">
            <div class="vd-dash-card-header">
                <span class="vd-dash-card-title">Appointment #<?= (int) $deposit['appointment_id'] ?></span>
                <span class="<?= depositStatusClass($deposit['deposit_status']) ?>"><?= htmlspecialchars($deposit['deposit_status']) ?></span>
            </div>
            <div class="vd-dash-card-body">
                <div class="row g-4">
                    <div class="col-12 col-lg-7">
                        <div class="vd-booking-profile-grid mb-3">
                            <div class="vd-booking-profile-item"><span>Clinic</span><strong><?= htmlspecialchars($deposit['clinic_name']) ?></strong></div>
                            <div class="vd-booking-profile-item"><span>Date</span><strong><?= date('F j, Y', strtotime($deposit['date'])) ?></strong></div>
                            <div class="vd-booking-profile-item"><span>Services</span><strong><?= htmlspecialchars($deposit['service_name'] ?: '—') ?></strong></div>
                            <div class="vd-booking-profile-item"><span>Required deposit</span><strong>₱<?= number_format((float) $deposit['amount'], 2) ?></strong></div>
                        </div>

                        <?php if ($deadline && $canSubmit): ?>
                            <div class="alert alert-warning small" data-payment-deadline="<?= htmlspecialchars(date(DATE_ATOM, strtotime($deadline))) ?>">
                                Submit your receipt within <strong data-countdown>calculating…</strong> to keep this slot.
                            </div>
                        <?php endif; ?>

                        <?php if ($deposit['deposit_status'] === 'Rejected'): ?>
                            <div class="alert alert-danger small">
                                <strong>Reason:</strong> <?= htmlspecialchars($deposit['rejection_reason'] ?: 'The submitted proof could not be verified.') ?>
                            </div>
                        <?php elseif ($deposit['deposit_status'] === 'Under Review'): ?>
                            <div class="alert alert-info small mb-0">Your receipt is waiting for staff verification. Your slot remains reserved.</div>
                        <?php elseif ($deposit['deposit_status'] === 'Verified'): ?>
                            <div class="alert alert-success small mb-0">Deposit verified. Your appointment is confirmed.</div>
                        <?php elseif ($deposit['deposit_status'] === 'Expired'): ?>
                            <div class="alert alert-secondary small mb-0">The payment deadline expired and this booking was cancelled.</div>
                        <?php endif; ?>
                    </div>

                    <?php if ($canSubmit): ?>
                    <div class="col-12 col-lg-5">
                        <div class="border rounded p-3 h-100">
                            <div class="text-center mb-3">
                                <?php if (!empty($settings['gcash_qr_path'])): ?>
                                    <img src="../../../public/assets/<?= htmlspecialchars($settings['gcash_qr_path']) ?>" alt="GCash payment QR code" class="img-fluid rounded" style="max-height:220px;">
                                <?php else: ?>
                                    <div class="vd-empty-state border rounded">GCash QR code has not been configured yet.</div>
                                <?php endif; ?>
                                <?php if (!empty($settings['gcash_account_name'])): ?>
                                    <strong class="d-block mt-2"><?= htmlspecialchars($settings['gcash_account_name']) ?></strong>
                                <?php endif; ?>
                                <?php if (!empty($settings['gcash_account_number'])): ?>
                                    <span class="text-muted small"><?= htmlspecialchars($settings['gcash_account_number']) ?></span>
                                <?php endif; ?>
                            </div>
                            <form class="depositSubmissionForm" enctype="multipart/form-data">
                                <input type="hidden" name="action" value="submit">
                                <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                <input type="hidden" name="appointment_id" value="<?= (int) $deposit['appointment_id'] ?>">
                                <div class="mb-3">
                                    <label class="vd-label form-label">GCash reference number</label>
                                    <input type="text" name="gcash_reference" class="form-control vd-input" maxlength="100" required>
                                </div>
                                <div class="mb-3">
                                    <label class="vd-label form-label">Payment receipt</label>
                                    <input type="file" name="receipt" class="form-control vd-input" accept="image/jpeg,image/png,application/pdf" required>
                                    <small class="text-muted">JPG, PNG, or PDF; maximum 5 MB.</small>
                                </div>
                                <div class="alert alert-danger d-none depositError"></div>
                                <button type="submit" class="btn vd-btn-gold w-100">Submit for Verification</button>
                            </form>
                        </div>
                    </div>
                    <?php endif; ?>
                </div>
            </div>
        </div>
        <?php endforeach; ?>
    <?php endif; ?>
</div>

<script>
(function () {
    document.querySelectorAll('[data-payment-deadline]').forEach(box => {
        const output = box.querySelector('[data-countdown]');
        const deadline = new Date(box.dataset.paymentDeadline).getTime();
        const render = () => {
            const remaining = Math.max(0, deadline - Date.now());
            const minutes = Math.floor(remaining / 60000);
            const seconds = Math.floor((remaining % 60000) / 1000);
            output.textContent = remaining > 0 ? `${minutes}:${String(seconds).padStart(2, '0')}` : 'expired';
        };
        render();
        const timer = setInterval(() => {
            render();
            if (Date.now() >= deadline) clearInterval(timer);
        }, 1000);
    });

    document.querySelectorAll('.depositSubmissionForm').forEach(form => {
        form.addEventListener('submit', async event => {
            event.preventDefault();
            const button = form.querySelector('button[type="submit"]');
            const errorBox = form.querySelector('.depositError');
            errorBox.classList.add('d-none');
            LoadingUI.setButton(button, true, 'Uploading…');
            try {
                const response = await fetch('../../controllers/depositController.php', {
                    method: 'POST',
                    body: new FormData(form)
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Submission failed.');
                window.showToast(result.message, true);
                document.querySelector('[data-page="billing-content.php"]')?.click();
            } catch (error) {
                errorBox.textContent = error.message || 'Unable to submit the receipt.';
                errorBox.classList.remove('d-none');
                LoadingUI.setButton(button, false);
            }
        });
    });
})();
</script>
