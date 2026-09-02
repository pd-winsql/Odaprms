<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'] ?? '', ['Admin', 'Dental Assistant'], true)) {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/depositModel.php';

$db = new Database();
$depositModel = new DepositModel($db->connect());
$depositModel->expireUnpaidAppointments();
$records = $depositModel->getAllRecords();
$depositStatuses = array_values(array_unique(array_filter(array_column($records, 'deposit_status'))));
sort($depositStatuses);
?>

<div class="d-flex flex-column gap-4">
    <div>
        <div class="vd-welcome-greet">BILLING</div>
        <div class="vd-welcome-name">Deposit Records</div>
        <p class="text-muted small mb-0 mt-2">Read-only record of appointment deposits. Review and approval actions are handled in Appointments.</p>
    </div>

    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">Payment history</span>
            <span class="vd-topbar-date" id="depositRecordCount"><?= count($records) ?> record<?= count($records) === 1 ? '' : 's' ?></span>
        </div>
        <div class="vd-filter-bar">
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="depositStatusFilter">Deposit status</label>
                <select id="depositStatusFilter" class="form-select vd-input vd-filter-select">
                    <option value="">All statuses</option>
                    <?php foreach ($depositStatuses as $status): ?>
                        <option value="<?= htmlspecialchars($status) ?>"><?= htmlspecialchars($status) ?></option>
                    <?php endforeach; ?>
                </select>
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="depositDateFrom">Appointment from</label>
                <input type="date" id="depositDateFrom" class="form-control vd-input vd-filter-select">
            </div>
            <div class="vd-filter-group">
                <label class="vd-label form-label" for="depositDateTo">Appointment to</label>
                <input type="date" id="depositDateTo" class="form-control vd-input vd-filter-select">
            </div>
            <div class="vd-filter-group vd-filter-clear">
                <button type="button" class="btn vd-btn-outline" id="clearDepositFilters">Clear</button>
            </div>
        </div>
        <div class="vd-dash-card-body">
        <?php if (!$records): ?>
            <div class="vd-empty-state">No deposit records found.</div>
        <?php else: ?>
            <div class="vd-appt-table-wrap">
                <table class="vd-appt-table vd-deposit-records-table w-100" id="depositRecordsTable">
                    <thead><tr><th>Patient</th><th>Appointment</th><th>Deposit</th><th>Status</th><th>Reviewed</th><th>Receipt</th></tr></thead>
                    <tbody>
                    <?php foreach ($records as $record): ?>
                        <tr data-status="<?= htmlspecialchars($record['deposit_status']) ?>" data-date="<?= htmlspecialchars($record['date']) ?>">
                            <td>
                                <div class="vd-appt-name"><?= htmlspecialchars($record['lastname'] . ', ' . $record['firstname']) ?></div>
                                <div class="vd-appt-meta"><?= htmlspecialchars($record['email']) ?></div>
                            </td>
                            <td>
                                <div class="vd-appt-name">#<?= (int)$record['appointment_id'] ?> · <?= date('M d, Y', strtotime($record['date'])) ?></div>
                                <div class="vd-appt-meta"><?= htmlspecialchars($record['clinic_name']) ?> · <?= htmlspecialchars($record['service_name'] ?: 'No service') ?></div>
                                <?php if (!empty($record['appointment_code'])): ?><div class="vd-appt-meta">Code: <?= htmlspecialchars($record['appointment_code']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <div class="vd-appt-name">₱<?= number_format((float)$record['amount'], 2) ?></div>
                                <?php if ($record['receipt_amount'] !== null): ?>
                                    <div class="vd-appt-meta">Receipt: ₱<?= number_format((float)$record['receipt_amount'], 2) ?></div>
                                <?php endif; ?>
                                <div class="vd-appt-meta">Ref: <?= htmlspecialchars($record['gcash_reference'] ?: 'Not submitted') ?></div>
                                <?php if ($record['gcash_transaction_at']): ?>
                                    <div class="vd-appt-meta">Paid <?= date('M d, Y g:i A', strtotime($record['gcash_transaction_at'])) ?></div>
                                <?php endif; ?>
                                <div class="vd-appt-meta"><?= $record['submitted_at'] ? date('M d, Y g:i A', strtotime($record['submitted_at'])) : 'No submission' ?></div>
                            </td>
                            <td>
                                <span class="<?= htmlspecialchars('vd-status vd-status-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $record['deposit_status']))) ?>"><?= htmlspecialchars($record['deposit_status']) ?></span>
                                <div class="vd-appt-meta mt-1">Appointment: <?= htmlspecialchars($record['appointment_status']) ?></div>
                                <?php if (!empty($record['rejection_reason'])): ?><div class="vd-appt-meta mt-1"><?= htmlspecialchars($record['rejection_reason']) ?></div><?php endif; ?>
                            </td>
                            <td>
                                <?php if ($record['verified_at']): ?>
                                    <div class="vd-appt-name"><?= htmlspecialchars($record['verified_by'] ?: 'Staff') ?></div>
                                    <div class="vd-appt-meta"><?= date('M d, Y g:i A', strtotime($record['verified_at'])) ?></div>
                                <?php else: ?><span class="vd-appt-meta">Not reviewed</span><?php endif; ?>
                            </td>
                            <td>
                                <?php if (!empty($record['has_receipt'])): ?>
                                    <button type="button" class="btn vd-btn-outline btn-sm" data-view-receipt
                                        data-receipt-url="../../controllers/depositController.php?action=receipt&amp;deposit_id=<?= (int)$record['deposit_id'] ?>"
                                        data-receipt-label="Receipt for appointment #<?= (int)$record['appointment_id'] ?>">View Receipt</button>
                                <?php else: ?><span class="vd-appt-meta">No receipt</span><?php endif; ?>
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
            <div class="modal-header"><div><div class="vd-action-modal-kicker">Payment proof</div><h5 class="modal-title vd-modal-title mb-0" id="receiptPreviewTitle">Receipt Preview</h5></div><button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button></div>
            <div class="modal-body p-0"><div class="vd-receipt-preview-loading" id="receiptPreviewLoading"><span class="vd-spinner" aria-hidden="true"></span><span>Loading receipt...</span></div><iframe id="receiptPreviewFrame" class="vd-receipt-preview-frame" title="GCash receipt preview"></iframe></div>
            <div class="modal-footer"><button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close Preview</button></div>
        </div>
    </div>
</div>

<script>
(function () {
    const table = document.getElementById('depositRecordsTable');
    const status = document.getElementById('depositStatusFilter');
    const from = document.getElementById('depositDateFrom');
    const to = document.getElementById('depositDateTo');
    const count = document.getElementById('depositRecordCount');
    const applyFilters = () => {
        if (!table) return;
        let visible = 0;
        table.querySelectorAll('tbody tr').forEach(row => {
            const show = (!status.value || row.dataset.status === status.value)
                && (!from.value || row.dataset.date >= from.value)
                && (!to.value || row.dataset.date <= to.value);
            row.style.display = show ? '' : 'none';
            if (show) visible++;
        });
        count.textContent = `${visible} record${visible === 1 ? '' : 's'}`;
    };
    [status, from, to].forEach(control => control?.addEventListener('change', applyFilters));
    document.getElementById('clearDepositFilters')?.addEventListener('click', () => { status.value = ''; from.value = ''; to.value = ''; applyFilters(); });

    const modalElement = document.getElementById('receiptPreviewModal');
    const modal = modalElement ? bootstrap.Modal.getOrCreateInstance(modalElement) : null;
    const frame = document.getElementById('receiptPreviewFrame');
    const loading = document.getElementById('receiptPreviewLoading');
    document.querySelectorAll('[data-view-receipt]').forEach(button => button.addEventListener('click', () => {
        document.getElementById('receiptPreviewTitle').textContent = button.dataset.receiptLabel || 'Receipt Preview';
        loading.classList.remove('d-none'); frame.classList.remove('is-ready'); frame.src = button.dataset.receiptUrl; modal.show();
    }));
    frame?.addEventListener('load', () => { loading.classList.add('d-none'); frame.classList.add('is-ready'); });
    modalElement?.addEventListener('hidden.bs.modal', () => { frame.removeAttribute('src'); frame.classList.remove('is-ready'); loading.classList.remove('d-none'); });
})();
</script>
