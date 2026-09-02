<?php
if (session_status() === PHP_SESSION_NONE) session_start();

require_once '../../config/conn.php';
require_once '../models/depositModel.php';
require_once '../models/siteSettingsModel.php';

$appointmentId = (int) ($_GET['appointment_id'] ?? 0);
$paymentToken = trim($_GET['token'] ?? '');
$db = new Database();
$conn = $db->connect();
$depositModel = new DepositModel($conn);
$depositModel->expireUnpaidAppointments();
$payment = $appointmentId > 0 && $paymentToken !== ''
    ? $depositModel->getPaymentContext($appointmentId, null, $paymentToken)
    : false;
$settings = (new SiteSettingsModel($conn))->getSettings();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$csrfToken = $_SESSION['csrf_token'];
$canSubmit = $payment
    && $payment['appointment_status'] === 'Awaiting Deposit'
    && in_array($payment['deposit_status'], ['Awaiting Submission', 'Rejected'], true);
$deadline = $payment ? ($payment['resubmission_deadline_at'] ?: $payment['payment_deadline_at']) : null;
?>
<!DOCTYPE html>
<html lang="en">
<head>
    <meta charset="UTF-8">
    <meta name="viewport" content="width=device-width, initial-scale=1.0">
    <meta name="referrer" content="no-referrer">
    <title>Appointment Deposit | Dr. Aprille Ventura Clinica Dental</title>
    <link rel="stylesheet" href="../../public/css/bootstrap.min.css">
    <link href="https://fonts.googleapis.com/css2?family=Cormorant+Garamond:wght@400;600&family=Jost:wght@300;400;500&display=swap" rel="stylesheet">
    <link rel="stylesheet" href="../../public/css/styles.css?v=<?= filemtime(__DIR__ . '/../../public/css/styles.css') ?>">
    <link rel="stylesheet" href="../../public/css/dashboard.css?v=<?= filemtime(__DIR__ . '/../../public/css/dashboard.css') ?>">
    <link rel="stylesheet" href="../../public/css/ui-refinements.css?v=<?= filemtime(__DIR__ . '/../../public/css/ui-refinements.css') ?>">
    <link rel="stylesheet" href="../../public/css/deposit-ocr.css?v=<?= filemtime(__DIR__ . '/../../public/css/deposit-ocr.css') ?>">
    <link rel="stylesheet" href="../../public/css/loading.css">
    <script src="../../public/js/loading.js" defer></script>
    <script src="../../public/js/deposit-ocr.js?v=<?= filemtime(__DIR__ . '/../../public/js/deposit-ocr.js') ?>" defer></script>
</head>
<body class="vd-form-page py-5">
<div class="container">
    <div class="row justify-content-center">
        <div class="col-12 col-lg-9">
            <div class="card vd-page-card border p-4 p-md-5">
                <div class="mb-4"><a href="../../index.php" class="btn vd-btn-outline btn-sm">&larr; Back to Home</a></div>

                <?php if (!$payment): ?>
                    <div class="vd-empty-state">This payment link is invalid or no longer available.</div>
                <?php else: ?>
                    <h1 class="vd-page-title mb-1">Complete Your Booking</h1>
                    <p class="text-muted mb-4">Submit the ₱<?= number_format((float) $payment['amount'], 2) ?> GCash deposit so clinic staff can confirm your appointment.</p>

                    <div class="row g-4">
                        <div class="col-12 col-lg-7">
                            <div class="border rounded p-3 mb-3">
                                <div><strong>Appointment #<?= (int) $payment['appointment_id'] ?></strong></div>
                                <div class="text-muted small mt-2"><?= htmlspecialchars($payment['firstname'] . ' ' . $payment['lastname']) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($payment['clinic_name']) ?> · <?= date('F j, Y', strtotime($payment['date'])) ?></div>
                                <div class="text-muted small"><?= htmlspecialchars($payment['service_name'] ?: '—') ?></div>
                            </div>

                            <?php if ($canSubmit): ?>
                                <div class="alert alert-warning" data-deadline="<?= htmlspecialchars(date(DATE_ATOM, strtotime($deadline))) ?>">
                                    Time remaining: <strong id="paymentCountdown">calculating…</strong>
                                </div>
                                <?php if ($payment['deposit_status'] === 'Rejected'): ?>
                                    <div class="alert alert-danger"><strong>Previous submission rejected:</strong> <?= htmlspecialchars($payment['rejection_reason']) ?></div>
                                <?php endif; ?>
                                <form id="publicDepositForm" enctype="multipart/form-data" data-deposit-ocr-form
                                    data-ocr-endpoint="../controllers/depositController.php"
                                    data-required-amount="<?= htmlspecialchars(number_format((float) $payment['amount'], 2, '.', '')) ?>">
                                    <input type="hidden" name="action" value="submit">
                                    <input type="hidden" name="csrf_token" value="<?= htmlspecialchars($csrfToken) ?>">
                                    <input type="hidden" name="appointment_id" value="<?= (int) $payment['appointment_id'] ?>">
                                    <input type="hidden" name="payment_token" value="<?= htmlspecialchars($paymentToken) ?>">
                                    <div class="vd-receipt-assistant">
                                        <div class="vd-receipt-assistant-head">
                                            <div class="vd-receipt-kicker">Payment proof</div>
                                            <h2 class="vd-receipt-assistant-title">Upload your GCash receipt</h2>
                                            <p class="vd-receipt-assistant-copy">A clear screenshot will automatically fill the payment details below.</p>
                                        </div>
                                        <div class="vd-receipt-assistant-body">
                                            <div class="vd-receipt-upload">
                                                <div class="vd-receipt-preview-shell">
                                                    <span class="vd-receipt-preview-empty" data-receipt-empty>No receipt selected</span>
                                                    <img class="d-none" data-receipt-preview alt="Selected GCash receipt preview">
                                                </div>
                                                <div class="min-w-0">
                                                    <label class="vd-label form-label" for="publicReceipt">Receipt screenshot</label>
                                                    <input id="publicReceipt" type="file" name="receipt" class="form-control vd-input" accept="image/jpeg,image/png" required>
                                                    <small class="vd-receipt-file-name" data-receipt-filename>JPG or PNG · maximum 5 MB</small>
                                                </div>
                                            </div>
                                            <div class="vd-ocr-status" data-ocr-status data-state="idle" role="status" aria-live="polite">
                                                <span data-ocr-message>Choose a receipt screenshot to fill the details automatically.</span>
                                            </div>
                                            <div class="vd-receipt-fields">
                                                <div>
                                                    <label class="form-label" for="publicReceiptAmount">Amount on receipt</label>
                                                    <input id="publicReceiptAmount" type="number" name="receipt_amount" class="form-control vd-input" min="0.01" step="0.01" inputmode="decimal" data-ocr-field required>
                                                </div>
                                                <div>
                                                    <label class="form-label" for="publicGcashReference">Reference number</label>
                                                    <input id="publicGcashReference" type="text" name="gcash_reference" class="form-control vd-input" maxlength="25" inputmode="numeric" data-ocr-field required>
                                                </div>
                                                <div class="vd-receipt-field-wide">
                                                    <label class="form-label" for="publicGcashTransaction">Transaction date and time</label>
                                                    <input id="publicGcashTransaction" type="datetime-local" name="gcash_transaction_at" class="form-control vd-input" data-ocr-field required>
                                                </div>
                                            </div>
                                            <p class="vd-receipt-review-note">Review these details before submitting. You may correct anything that was read incorrectly.</p>
                                            <button type="submit" class="btn vd-btn-gold w-100">Submit for Verification</button>
                                        </div>
                                    </div>
                                    <div id="paymentError" class="alert alert-danger d-none"></div>
                                </form>
                            <?php elseif ($payment['deposit_status'] === 'Under Review'): ?>
                                <div class="alert alert-info">Your receipt has been submitted and is waiting for clinic verification.</div>
                            <?php elseif ($payment['deposit_status'] === 'Verified'): ?>
                                <div class="alert alert-success">Your payment was verified and the appointment is confirmed.</div>
                            <?php else: ?>
                                <div class="alert alert-secondary">This booking is <?= htmlspecialchars(strtolower($payment['deposit_status'])) ?>.</div>
                            <?php endif; ?>
                        </div>
                        <div class="col-12 col-lg-5 text-center">
                            <div class="border rounded p-3">
                                <h2 class="h5">Pay with GCash</h2>
                                <div class="display-6 mb-3">₱<?= number_format((float) $payment['amount'], 2) ?></div>
                                <?php if (!empty($settings['gcash_qr_path'])): ?>
                                    <img src="../../public/assets/<?= htmlspecialchars($settings['gcash_qr_path']) ?>" alt="GCash payment QR code" class="img-fluid rounded" style="max-height:260px;">
                                <?php else: ?>
                                    <div class="vd-empty-state border rounded">GCash QR code has not been configured yet.</div>
                                <?php endif; ?>
                                <?php if (!empty($settings['gcash_account_name'])): ?><strong class="d-block mt-3"><?= htmlspecialchars($settings['gcash_account_name']) ?></strong><?php endif; ?>
                                <?php if (!empty($settings['gcash_account_number'])): ?><span class="text-muted"><?= htmlspecialchars($settings['gcash_account_number']) ?></span><?php endif; ?>
                            </div>
                        </div>
                    </div>
                <?php endif; ?>
            </div>
        </div>
    </div>
</div>

<?php if ($canSubmit): ?>
<script>
const deadline = new Date(<?= json_encode(date(DATE_ATOM, strtotime($deadline))) ?>).getTime();
const countdown = document.getElementById('paymentCountdown');
const renderCountdown = () => {
    const remaining = Math.max(0, deadline - Date.now());
    const minutes = Math.floor(remaining / 60000);
    const seconds = Math.floor((remaining % 60000) / 1000);
    countdown.textContent = remaining ? `${minutes}:${String(seconds).padStart(2, '0')}` : 'expired';
};
renderCountdown();
setInterval(renderCountdown, 1000);

document.getElementById('publicDepositForm').addEventListener('submit', async function (event) {
    event.preventDefault();
    const button = this.querySelector('button[type="submit"]');
    const errorBox = document.getElementById('paymentError');
    errorBox.classList.add('d-none');
    LoadingUI.setButton(button, true, 'Uploading…');
    try {
        const response = await fetch('../controllers/depositController.php', { method: 'POST', body: new FormData(this) });
        const result = await response.json();
        if (!result.success) throw new Error(result.message || 'Submission failed.');
        window.location.reload();
    } catch (error) {
        errorBox.textContent = error.message || 'Unable to submit the receipt.';
        errorBox.classList.remove('d-none');
        LoadingUI.setButton(button, false);
    }
});
</script>
<?php endif; ?>
</body>
</html>
