<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Dental Assistant') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/patientModel.php';
require_once __DIR__ . '/../../../models/appointmentModel.php';
require_once __DIR__ . '/../../../helpers/serviceImage.php';

$patient_id = $_GET['id'] ?? null;

if (!$patient_id) {
    echo '<div class="vd-empty-state">No patient specified.</div>';
    exit;
}

$db   = new Database();
$conn = $db->connect();

$patientModel     = new Patient($conn);
$appointmentModel = new Appointment($conn);

$patient      = $patientModel->getPatient($patient_id);
$transactions = $appointmentModel->getPatientTransactionHistory($patient_id);
$servicesByAppointment = $appointmentModel->getServiceDetailsForAppointments(array_column($transactions, 'appointment_id'));

if (!$patient) {
    echo '<div class="vd-empty-state">Patient not found.</div>';
    exit;
}

function txStatusClass($status) {
    return 'vd-status vd-status-' . strtolower(preg_replace('/[^A-Za-z0-9]+/', '-', $status));
}

function patientTransactionDetailsPayload(array $transaction, array $services, array $patient): string {
    if (!$services && !empty($transaction['service_name'])) {
        $services[] = [
            'service_name' => $transaction['service_name'],
            'service_description' => '',
            'category_name' => 'Dental service',
        ];
    }

    return htmlspecialchars(json_encode([
        'appointmentId' => (int) $transaction['appointment_id'],
        'appointmentCode' => $transaction['appointment_code'] ?? '',
        'patientName' => trim(($patient['firstname'] ?? '') . ' ' . ($patient['lastname'] ?? '')),
        'email' => $patient['email'] ?? '',
        'clinic' => $transaction['clinic_name'] ?? 'Not recorded',
        'date' => date('F j, Y', strtotime($transaction['date'])),
        'time' => date('g:i A', strtotime($transaction['start_time'])) . '–' . date('g:i A', strtotime($transaction['end_time'])),
        'status' => $transaction['status'] ?? 'Not recorded',
        'services' => array_map(static fn(array $service): array => [
            'name' => $service['service_name'] ?? 'Service',
            'category' => $service['category_name'] ?? 'Dental service',
            'description' => $service['service_description'] ?? '',
            'image' => vdServiceImageUrl($service['service_image'] ?? null, '../../../'),
        ], $services),
    ], JSON_UNESCAPED_UNICODE | JSON_UNESCAPED_SLASHES), ENT_QUOTES, 'UTF-8');
}
?>

<div class="mb-3">
    <button id="backToPatients" class="btn vd-btn-outline vd-back-btn">
        &larr; Back to Patients
    </button>
</div>

<div class="d-flex flex-column gap-4">




    <div class="vd-dash-card">
        <div class="vd-dash-card-header">
            <span class="vd-dash-card-title">
                Transaction History — <?= htmlspecialchars($patient['lastname'] . ', ' . $patient['firstname']) ?>
            </span>
            <span class="vd-topbar-date"><?= count($transactions) ?> total</span>
        </div>

        <div class="vd-dash-card-body">
            

            <?php if (empty($transactions)): ?>
                <div class="vd-empty-state">No transaction history found for this patient.</div>
            <?php else: ?>
                <div class="vd-appt-table-wrap">
                    <table class="vd-appt-table w-100">
                        <thead>
                            <tr>
                                <th>Service</th>
                                <th>Clinic</th>
                                <th>Date</th>
                                <th>Status</th>
                                <th class="vd-table-actions-column">Action</th>
                            </tr>
                        </thead>
                        <tbody>
                            <?php foreach ($transactions as $t): ?>
                                <tr>
                                    <td class="vd-appt-meta"><?= htmlspecialchars($t['service_name']) ?></td>
                                    <td class="vd-appt-meta"><?= htmlspecialchars($t['clinic_name'] ?? '—') ?></td>
                                    <td class="vd-appt-meta"><?= date('M d, Y', strtotime($t['date'])) ?></td>
                                    <td>
                                        <span class="<?= txStatusClass($t['status']) ?>">
                                            <?= htmlspecialchars($t['status']) ?>
                                        </span>
                                    </td>
                                    <td class="vd-table-actions-column vd-patient-transaction-action-cell">
                                        <button type="button" class="btn vd-btn-outline vd-patient-transaction-details"
                                            data-patient-transaction-details="<?= patientTransactionDetailsPayload($t, $servicesByAppointment[(int) $t['appointment_id']] ?? [], $patient) ?>"
                                            aria-label="View appointment details for <?= date('F j, Y', strtotime($t['date'])) ?>">
                                            <i class="ti ti-eye" aria-hidden="true"></i>
                                            <span>View details</span>
                                        </button>
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

<div class="modal fade vd-appointment-details-modal vd-patient-transaction-modal" id="patientTransactionDetailsModal" tabindex="-1"
    aria-labelledby="patientTransactionDetailsTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-dialog-scrollable">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-appointment-details-kicker">Past transaction</div>
                    <h5 class="modal-title vd-modal-title" id="patientTransactionDetailsTitle">Appointment details</h5>
                    <p class="vd-appointment-details-subtitle mb-0" id="patientTransactionDetailsSubtitle"></p>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <section aria-labelledby="patientTransactionInformationHeading">
                    <h6 class="vd-appointment-details-section-title" id="patientTransactionInformationHeading">Appointment information</h6>
                    <div class="vd-appointment-detail-grid" id="patientTransactionDetailGrid"></div>
                </section>
                <section class="vd-appointment-services-section" aria-labelledby="patientTransactionServicesHeading">
                    <div class="vd-appointment-section-heading">
                        <h6 class="vd-appointment-details-section-title mb-0" id="patientTransactionServicesHeading">Selected services</h6>
                        <span class="vd-appointment-service-count" id="patientTransactionServiceCount"></span>
                    </div>
                    <div class="vd-appointment-service-list" id="patientTransactionServiceList"></div>
                </section>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<script>
(function () {
    const backBtn = document.getElementById('backToPatients');
    if (backBtn) {
        backBtn.addEventListener('click', async () => {
            if (typeof loadpage === 'function') {
                await loadpage('patient-content.php');
            }
        });
    }

    function appendDetail(container, label, value, valueClass = '') {
        const item = document.createElement('div');
        item.className = 'vd-appointment-detail-item';
        const labelElement = document.createElement('span');
        labelElement.textContent = label;
        const valueElement = document.createElement('strong');
        valueElement.textContent = value || 'Not recorded';
        if (valueClass) valueElement.classList.add(valueClass);
        item.append(labelElement, valueElement);
        container.appendChild(item);
    }

    document.querySelectorAll('[data-patient-transaction-details]').forEach(button => {
        button.addEventListener('click', () => {
            try {
                const details = JSON.parse(button.dataset.patientTransactionDetails);
                const modalElement = document.getElementById('patientTransactionDetailsModal');
                const grid = document.getElementById('patientTransactionDetailGrid');
                const serviceList = document.getElementById('patientTransactionServiceList');
                const services = Array.isArray(details.services) ? details.services : [];

                document.getElementById('patientTransactionDetailsTitle').textContent = details.patientName || 'Patient appointment';
                document.getElementById('patientTransactionDetailsSubtitle').textContent = `${details.date} · ${details.clinic}`;
                grid.replaceChildren();
                appendDetail(grid, 'Appointment number', `#${details.appointmentId}`);
                appendDetail(grid, 'Status', details.status, 'vd-appointment-detail-status');
                appendDetail(grid, 'Clinic', details.clinic);
                appendDetail(grid, 'Date', details.date);
                appendDetail(grid, 'Clinic window', details.time);
                appendDetail(grid, 'Appointment code', details.appointmentCode || 'Not issued');
                appendDetail(grid, 'Patient email', details.email);

                document.getElementById('patientTransactionServiceCount').textContent = `${services.length} service${services.length === 1 ? '' : 's'}`;
                serviceList.replaceChildren();
                if (!services.length) {
                    const empty = document.createElement('div');
                    empty.className = 'vd-empty-state vd-appointment-services-empty';
                    empty.textContent = 'No services are linked to this appointment.';
                    serviceList.appendChild(empty);
                } else {
                    services.forEach(service => {
                        const card = document.createElement('article');
                        card.className = 'vd-appointment-service-card';
                        const media = document.createElement('span');
                        media.className = 'vd-appointment-service-media';
                        if (service.image) {
                            const image = document.createElement('img');
                            image.src = service.image;
                            image.alt = '';
                            image.loading = 'lazy';
                            media.appendChild(image);
                        } else {
                            media.textContent = 'Image pending';
                        }
                        const copy = document.createElement('span');
                        copy.className = 'vd-appointment-service-copy';
                        const category = document.createElement('span');
                        category.className = 'vd-appointment-service-category';
                        category.textContent = service.category || 'Dental service';
                        const name = document.createElement('strong');
                        name.textContent = service.name || 'Service';
                        copy.append(category, name);
                        if (service.description) {
                            const description = document.createElement('small');
                            description.textContent = service.description;
                            copy.appendChild(description);
                        }
                        card.append(media, copy);
                        serviceList.appendChild(card);
                    });
                }

                bootstrap.Modal.getOrCreateInstance(modalElement).show();
            } catch (error) {
                window.showToast?.('Unable to display the appointment details.', false);
            }
        });
    });
})();
</script>
