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
                                <button type="button" class="btn vd-btn-outline btn-sm" data-view-receipt
                                   data-receipt-url="../../controllers/depositController.php?action=receipt&amp;deposit_id=<?= (int) $review['deposit_id'] ?>"
                                   data-receipt-label="Receipt for appointment #<?= (int) $review['appointment_id'] ?>">
                                    View Receipt
                                </button>
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

<div class="modal fade" id="receiptPreviewModal" tabindex="-1" aria-labelledby="receiptPreviewTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-xl vd-receipt-preview-dialog">
        <div class="modal-content vd-modal-content vd-receipt-preview-modal">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Payment proof</div>
                    <h5 class="modal-title vd-modal-title mb-0" id="receiptPreviewTitle">Receipt Preview</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body p-0">
                <div class="vd-receipt-preview-loading" id="receiptPreviewLoading">
                    <span class="vd-spinner" aria-hidden="true"></span>
                    <span>Loading receipt…</span>
                </div>
                <iframe id="receiptPreviewFrame" class="vd-receipt-preview-frame" title="GCash receipt preview"></iframe>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close Preview</button>
            </div>
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
    const receiptModalElement = document.getElementById('receiptPreviewModal');
    const receiptModal = receiptModalElement ? bootstrap.Modal.getOrCreateInstance(receiptModalElement) : null;
    const receiptFrame = document.getElementById('receiptPreviewFrame');
    const receiptLoading = document.getElementById('receiptPreviewLoading');
    let receiptButton = null;

    document.querySelectorAll('[data-view-receipt]').forEach(button => {
        button.addEventListener('click', () => {
            receiptButton = button;
            document.getElementById('receiptPreviewTitle').textContent = button.dataset.receiptLabel || 'Receipt Preview';
            receiptLoading.classList.remove('d-none');
            receiptFrame.classList.remove('is-ready');
            LoadingUI.setButton(button, true, 'Loading…');
            receiptFrame.src = button.dataset.receiptUrl;
            receiptModal.show();
        });
    });

    receiptFrame?.addEventListener('load', () => {
        receiptLoading.classList.add('d-none');
        receiptFrame.classList.add('is-ready');
        if (receiptButton) LoadingUI.setButton(receiptButton, false);
    });

    receiptModalElement?.addEventListener('hidden.bs.modal', () => {
        if (receiptButton) LoadingUI.setButton(receiptButton, false);
        receiptButton = null;
        receiptFrame.removeAttribute('src');
        receiptFrame.classList.remove('is-ready');
        receiptLoading.classList.remove('d-none');
    });

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
            const confirmation = await window.showActionModal({
                title: 'Approve GCash Deposit',
                kicker: 'Payment verification',
                message: 'Confirm that the payment appears in the clinic’s GCash account. This will confirm the appointment and generate the patient code.',
                confirmText: 'Approve Payment',
                icon: 'ti-receipt-check',
                tone: 'success'
            });
            if (!confirmation.confirmed) return;
            LoadingUI.setButton(button, true, 'Approving…');
            try { await sendAction('verify', button.dataset.verifyDeposit); }
            catch (error) { window.showToast(error.message, false); LoadingUI.setButton(button, false); }
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
        LoadingUI.setButton(this, true, 'Rejecting…');
        try { await sendAction('reject', rejectionDepositId, reason); }
        catch (error) { errorBox.textContent = error.message; errorBox.classList.remove('d-none'); LoadingUI.setButton(this, false); }
    });
})();
</script>
