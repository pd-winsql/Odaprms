<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/depositModel.php';

$db = new Database();
$conn = $db->connect();
$depositModel = new DepositModel($conn);
$depositModel->expireUnpaidAppointments();
$reviews = $depositModel->getPendingReviews();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
?>

<div class="d-flex flex-column gap-4">
    <div>
        <div class="vd-welcome-greet">BILLING</div>
        <div class="vd-welcome-name">GCash Deposit Verification</div>
        <p class="text-muted small mb-0 mt-2">Review submitted ₱400 deposits. Appointments appear in the regular appointment list only after approval.</p>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Awaiting Review</span>
            <span class="vd-topbar-date"><?= count($reviews) ?> payment<?= count($reviews) === 1 ? '' : 's' ?></span>
        </div>
        <div class="vd-dash-card-body">
        <?php if (!$reviews): ?>
            <div class="vd-empty-state">No payments are awaiting verification.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
                <table class="vd-appt-table w-100">
                    <thead><tr><th>Patient</th><th>Appointment</th><th>Payment</th><th>Receipt</th><th>Action</th></tr></thead>
                    <tbody>
                    <?php foreach ($reviews as $review): ?>
                        <tr>
                            <td>
                                <div class="vd-appt-name"><?= htmlspecialchars($review['lastname'] . ', ' . $review['firstname']) ?></div>
                                <div class="vd-appt-meta"><?= htmlspecialchars($review['email']) ?></div>
                            </td>
                            <td>
                                <div class="vd-appt-name">#<?= (int) $review['appointment_id'] ?> · <?= date('M d, Y', strtotime($review['date'])) ?></div>
                                <div class="vd-appt-meta"><?= htmlspecialchars($review['clinic_name']) ?> · <?= htmlspecialchars($review['service_name'] ?: '—') ?></div>
                            </td>
                            <td>
                                <div class="vd-appt-name">₱<?= number_format((float) $review['amount'], 2) ?></div>
                                <div class="vd-appt-meta">Ref: <?= htmlspecialchars($review['gcash_reference']) ?></div>
                                <div class="vd-appt-meta"><?= date('M d, Y g:i A', strtotime($review['submitted_at'])) ?></div>
                            </td>
                            <td>
                                <a class="btn vd-btn-outline btn-sm" target="_blank" rel="noopener"
                                   href="../../controllers/depositController.php?action=receipt&amp;deposit_id=<?= (int) $review['deposit_id'] ?>">
                                    View Receipt
                                </a>
                            </td>
                            <td>
                                <div class="d-flex flex-wrap gap-2">
                                    <button type="button" class="btn vd-btn-gold btn-sm" data-verify-deposit="<?= (int) $review['deposit_id'] ?>">Approve</button>
                                    <button type="button" class="btn vd-btn-outline btn-sm" data-reject-deposit="<?= (int) $review['deposit_id'] ?>" data-bs-toggle="modal" data-bs-target="#rejectDepositModal">Reject</button>
                                </div>
                            </td>
                        </tr>
                    <?php endforeach; ?>
                    </tbody>
                </table>
            </div>
        <?php endif; ?>
        </div>
    </div>
</div>

<div class="modal fade" id="rejectDepositModal" tabindex="-1" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content">
            <div class="modal-header border-0"><h5 class="modal-title vd-modal-title">Reject Payment</h5><button type="button" class="btn-close" data-bs-dismiss="modal"></button></div>
            <div class="modal-body">
                <label class="vd-label form-label">Reason shown to the patient</label>
                <textarea id="depositRejectionReason" class="form-control vd-input" rows="3" maxlength="255" required></textarea>
                <div id="depositReviewError" class="alert alert-danger d-none mt-3"></div>
            </div>
            <div class="modal-footer border-0">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn vd-btn-gold" id="confirmDepositRejection">Reject Payment</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const csrfToken = <?= json_encode($csrfToken) ?>;
    const controller = '../../controllers/depositController.php';
    let rejectionDepositId = null;

    async function sendAction(action, depositId, reason = '') {
        const body = new FormData();
        body.append('action', action);
        body.append('deposit_id', depositId);
        body.append('csrf_token', csrfToken);
        if (reason) body.append('reason', reason);
        const response = await fetch(controller, { method: 'POST', body });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Action failed.');
        window.showToast(result.message, true);
        document.querySelector('[data-page="payment-review-content.php"]')?.click();
    }

    document.querySelectorAll('[data-verify-deposit]').forEach(button => {
        button.addEventListener('click', async () => {
            if (!confirm('Approve this GCash payment and confirm the appointment?')) return;
            button.disabled = true;
            try { await sendAction('verify', button.dataset.verifyDeposit); }
            catch (error) { window.showToast(error.message, false); button.disabled = false; }
        });
    });

    document.querySelectorAll('[data-reject-deposit]').forEach(button => {
        button.addEventListener('click', () => {
            rejectionDepositId = button.dataset.rejectDeposit;
            document.getElementById('depositRejectionReason').value = '';
            document.getElementById('depositReviewError').classList.add('d-none');
        });
    });

    document.getElementById('confirmDepositRejection')?.addEventListener('click', async function () {
        const reason = document.getElementById('depositRejectionReason').value.trim();
        const errorBox = document.getElementById('depositReviewError');
        if (reason.length < 3) {
            errorBox.textContent = 'Enter a rejection reason.';
            errorBox.classList.remove('d-none');
            return;
        }
        this.disabled = true;
        try { await sendAction('reject', rejectionDepositId, reason); }
        catch (error) { errorBox.textContent = error.message; errorBox.classList.remove('d-none'); this.disabled = false; }
    });
})();
</script>
