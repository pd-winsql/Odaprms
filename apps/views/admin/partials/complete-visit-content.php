<?php
if (session_status() === PHP_SESSION_NONE) session_start();
if (($_SESSION['user_role'] ?? '') !== 'Admin' || !isset($_SESSION['user_id'])) {
    http_response_code(403);
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    return;
}
require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';
require_once __DIR__ . '/../../../models/serviceModel.php';
require_once __DIR__ . '/../../../helpers/odontogramView.php';
$conn = (new Database())->connect();
$id = filter_var($_GET['complete_visit'] ?? null, FILTER_VALIDATE_INT, ['options' => ['min_range' => 1]]);
$stmt = $conn->prepare('SELECT a.*, p.verified_deposit FROM vw_appointment_overview a LEFT JOIN vw_appointment_payment_summary p ON p.appointment_id = a.appointment_id WHERE a.appointment_id = :id');
$stmt->execute([':id' => $id ?: 0]);
$visit = $stmt->fetch(PDO::FETCH_ASSOC);
if (!$visit || $visit['status'] !== 'In Progress') {
    http_response_code($visit ? 409 : 404);
    echo '<div class="vd-empty-state">This visit is unavailable for settlement. <a href="dashboard.php">Back to queue</a></div>';
    return;
}
$appointmentRules = require __DIR__ . '/../../../../config/appointment.php';
$maxServicesPerVisit = max(1, (int) ($appointmentRules['max_services_per_visit'] ?? 5));
$isAdminQueueView = true;
$billingServicesByCategory = [];
if ($isAdminQueueView) {
    $serviceModel = new ServiceModel($conn);
    $serviceCategoryNames = array_column($serviceModel->getAllCategories(), 'category_name', 'category_id');
    foreach ($serviceModel->getAllServices() as $service) {
        $categoryId = (int) ($service['category_id'] ?? 0);
        $categoryName = $serviceCategoryNames[$categoryId] ?? 'Other services';
        $billingServicesByCategory[$categoryName][] = $service;
    }
}

$serviceDetails = (new Appointment($conn))->getServiceDetailsForAppointments([(int) $id]);
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
$patientName = trim($visit['firstname'] . ' ' . $visit['lastname']);
$pageData = [
    'id' => (int) $id, 'patientId' => (int) $visit['patient_id'],
    'patient' => $patientName, 'deposit' => (float) ($visit['verified_deposit'] ?? 0),
    'originalServiceIds' => array_map('intval', array_column($serviceDetails[(int) $id] ?? [], 'service_id')),
    'maxServices' => $maxServicesPerVisit, 'csrfToken' => $_SESSION['csrf_token'],
];
?>
<article class="vd-complete-visit" id="completeVisitPage">
    <a class="vd-complete-visit-back" href="dashboard.php"><i class="ti ti-arrow-left" aria-hidden="true"></i> Today’s Queue</a>
    <header class="vd-complete-visit-header">
        <div><span class="vd-action-modal-kicker">Complete visit · #<?= (int) $id ?></span>
        <h1><?= htmlspecialchars($patientName) ?></h1>
        <p><?= htmlspecialchars($visit['clinic_name']) ?> · <?= htmlspecialchars($visit['date']) ?></p></div>
        <span class="vd-status vd-status-in-progress">In treatment</span>
    </header>
    <div class="vd-complete-visit-grid">
        <div class="vd-complete-visit-panel">                <section class="vd-billing-service-editor mb-4" aria-labelledby="finalPerformedServicesHeading">
                    <div class="vd-billing-service-editor-head">
                        <div>
                            <h6 id="finalPerformedServicesHeading">Services performed</h6>
                            <p>Select the treatments actually provided during this visit.</p>
                        </div>
                        <span id="finalServiceSelectionCount">0 of <?= $maxServicesPerVisit ?> selected</span>
                    </div>
                    <div class="vd-billing-service-groups">
                        <?php foreach ($billingServicesByCategory as $categoryName => $services): ?>
                            <fieldset class="vd-billing-service-group">
                                <legend><?= htmlspecialchars($categoryName) ?></legend>
                                <div class="vd-billing-service-options">
                                    <?php foreach ($services as $service): ?>
                                        <label class="vd-billing-service-option">
                                            <input type="checkbox"
                                                value="<?= (int) $service['service_id'] ?>"
                                                data-final-service
                                                data-service-name="<?= htmlspecialchars($service['service_name'], ENT_QUOTES) ?>"
                                                data-service-active="<?= (int) $service['is_active'] ?>">
                                            <span>
                                                <strong><?= htmlspecialchars($service['service_name']) ?></strong>
                                                <?php if (!(int) $service['is_active']): ?><small>Inactive</small><?php endif; ?>
                                            </span>
                                            <i class="ti ti-check" aria-hidden="true"></i>
                                        </label>
                                    <?php endforeach; ?>
                                </div>
                            </fieldset>
                        <?php endforeach; ?>
                    </div>
                    <div class="vd-billing-service-feedback" id="finalServiceSelectionFeedback" aria-live="polite"></div>
                    <div class="mt-3 d-none" id="finalServiceChangeReasonGroup">
                        <label class="vd-label form-label" for="finalServiceChangeReason">Reason for service change</label>
                        <textarea class="form-control vd-input" id="finalServiceChangeReason" rows="2" minlength="3" maxlength="255" placeholder="Example: Dentist recommended a more appropriate treatment."></textarea>
                        <small class="text-muted">Required when the performed services differ from the booking.</small>
                    </div>
                </section>
</div>
        <aside class="vd-complete-visit-panel vd-complete-visit-payment" aria-labelledby="completeVisitPaymentHeading">
            <h2 id="completeVisitPaymentHeading">Payment &amp; settlement</h2>
            <p>Verified deposit: <?= htmlspecialchars(number_format($pageData['deposit'], 2)) ?> PHP</p>
                            <div class="row g-3">
                    <div class="col-md-6"><label class="vd-label form-label" for="finalServiceAmount">Actual treatment charge</label><input type="number" min="0" step="0.01" class="form-control vd-input" id="finalServiceAmount" required></div>
                    <div class="col-md-6"><label class="vd-label form-label" for="finalCashTendered">Cash tendered</label><input type="number" min="0" step="0.01" class="form-control vd-input" id="finalCashTendered" value="0" required></div>
                    <div class="col-12"><label class="vd-label form-label" for="finalBillingNotes">Billing notes (optional)</label><textarea class="form-control vd-input" id="finalBillingNotes" rows="2" maxlength="255"></textarea></div>
                </div>
                <div class="vd-final-billing-summary mt-4">
                    <div><span>Actual charge</span><strong id="finalChargeDisplay">₱0.00</strong></div>
                    <div><span>Deposit applied</span><strong id="finalDepositDisplay">−₱0.00</strong></div>
                    <div class="vd-final-billing-total"><span>Amount due</span><strong id="finalAmountDueDisplay">₱0.00</strong></div>
                    <div><span>Cash tendered</span><strong id="finalCashDisplay">₱0.00</strong></div>
                    <div><span>Change</span><strong id="finalChangeDisplay">₱0.00</strong></div>
                </div>
                <div class="alert alert-danger d-none mt-3 mb-0" id="finalBillingError"></div>

            <button type="button" class="btn vd-btn-gold w-100 mt-3" id="recordPaymentAndComplete" disabled>Record payment &amp; complete visit</button>
        </aside>
    </div>
    <details class="vd-complete-visit-chart" id="completeVisitChart">
        <summary><span><strong>Dental chart</strong><small>Optional · open to view or update findings</small></span><i class="ti ti-chevron-down" aria-hidden="true"></i></summary>
        <?php vdRenderOdontogramWorkspace('completeVisitOdontogram', false); ?>
    </details>
</article>
<script type="application/json" id="completeVisitData"><?= json_encode($pageData, JSON_HEX_TAG | JSON_HEX_AMP | JSON_HEX_APOS | JSON_HEX_QUOT) ?></script>

