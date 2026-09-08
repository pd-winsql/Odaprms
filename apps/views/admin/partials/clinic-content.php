<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || ($_SESSION['user_role'] ?? '') !== 'Dental Assistant') {
    echo '<div class="vd-empty-state">Unauthorized.</div>';
    exit;
}

require_once __DIR__ . '/../../../../config/conn.php';
require_once __DIR__ . '/../../../models/clinicModel.php';

$db   = new Database();
$conn = $db->connect();
$clinicModel = new Clinic($conn);

$clinics = $clinicModel->getAllClinics();
$_SESSION['csrf_token'] ??= bin2hex(random_bytes(32));
?>

<div class="d-flex flex-column gap-4">
    <div class="vd-dash-card vd-clinic-management-card">
        <div class="vd-dash-card-header">
            <div>
                <span class="vd-dash-card-title">Manage Clinics</span>
                <span class="vd-clinic-count ms-2"><?= count($clinics) ?> location<?= count($clinics) === 1 ? '' : 's' ?></span>
                <p class="text-muted small mb-0 mt-1">Keep branch details and patient-facing map information current.</p>
            </div>
            <button type="button" class="btn vd-btn-gold" id="addClinicBtn">
                <i class="ti ti-plus" aria-hidden="true"></i> Add Clinic
            </button>
        </div>

        <div class="vd-clinic-list">
            <?php if (empty($clinics)): ?>
                <div class="vd-empty-state">No clinics found. Add a location to begin managing branches.</div>
            <?php endif; ?>

            <?php foreach ($clinics as $clinic):
                $embedUrl = trim((string)($clinic['embed_url'] ?? ''));
                $mapParts = $embedUrl !== '' ? parse_url($embedUrl) : false;
                $mapHost = strtolower((string)($mapParts['host'] ?? ''));
                $mapPath = strtolower((string)($mapParts['path'] ?? ''));
                $hasMap = in_array($mapHost, ['www.google.com', 'google.com', 'maps.google.com'], true)
                    && str_starts_with($mapPath, '/maps/embed');
                $safeEmbedUrl = $hasMap ? $embedUrl : '';
                $clinicInUse = (int)($clinic['schedule_count'] ?? 0) > 0
                    || (int)($clinic['appointment_count'] ?? 0) > 0;
                $canDelete = !$clinicInUse && count($clinics) > 1;
                $deleteUnavailableReason = $clinicInUse
                    ? 'Clinics in use cannot be deleted'
                    : 'At least one clinic must remain';
            ?>
                <div class="vd-clinic-list-row"
                     data-clinic-id="<?= (int)$clinic['clinic_id'] ?>"
                     data-name="<?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>"
                     data-address="<?= htmlspecialchars($clinic['clinic_address'], ENT_QUOTES) ?>"
                     data-embed-url="<?= htmlspecialchars($safeEmbedUrl, ENT_QUOTES) ?>">
                    <div class="vd-clinic-list-main">
                        <div class="vd-clinic-list-icon" aria-hidden="true">
                            <i class="ti ti-building-hospital"></i>
                        </div>
                        <div class="vd-clinic-list-copy">
                            <div class="vd-clinic-list-name"><?= htmlspecialchars($clinic['clinic_name']) ?></div>
                            <div class="vd-clinic-list-address">
                                <i class="ti ti-map-pin" aria-hidden="true"></i>
                                <span><?= htmlspecialchars($clinic['clinic_address']) ?></span>
                            </div>
                        </div>
                    </div>

                    <div class="vd-clinic-list-meta">
                        <span class="vd-clinic-map-status <?= $hasMap ? 'is-ready' : 'is-missing' ?>">
                            <i class="ti <?= $hasMap ? 'ti-map-check' : 'ti-map-off' ?>" aria-hidden="true"></i>
                            <?= $hasMap ? 'Map ready' : 'No map' ?>
                        </span>
                        <div class="vd-clinic-actions" role="group" aria-label="Actions for <?= htmlspecialchars($clinic['clinic_name'], ENT_QUOTES) ?>">
                            <button type="button" class="btn vd-clinic-action-btn vd-view-clinic-map-btn"
                                    title="View map" aria-label="View map" <?= $hasMap ? '' : 'disabled' ?>>
                                <i class="ti ti-map-pin" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn vd-clinic-action-btn vd-edit-clinic-btn"
                                    title="Edit clinic" aria-label="Edit clinic">
                                <i class="ti ti-pencil" aria-hidden="true"></i>
                            </button>
                            <button type="button" class="btn vd-clinic-action-btn vd-delete-clinic-btn"
                                    title="<?= $canDelete ? 'Delete clinic' : $deleteUnavailableReason ?>"
                                    aria-label="<?= $canDelete ? 'Delete clinic' : $deleteUnavailableReason ?>"
                                    <?= $canDelete ? '' : 'disabled' ?>>
                                <i class="ti ti-trash" aria-hidden="true"></i>
                            </button>
                        </div>
                    </div>
                </div>
            <?php endforeach; ?>
        </div>
    </div>

</div>

<div class="modal fade" id="clinicModal" tabindex="-1" aria-labelledby="clinicModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vd-modal-content">
            <form id="clinicForm" novalidate>
                <input type="hidden" id="clinicModalId" name="clinic_id">
                <div class="modal-header">
                    <div>
                        <div class="vd-action-modal-kicker">Clinic management</div>
                        <h5 class="modal-title vd-modal-title mb-0" id="clinicModalTitle">Add New Clinic</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <p class="vd-clinic-modal-intro">Enter the branch details shown to patients during booking.</p>
                    <div id="clinicModalError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="vd-label form-label" for="clinicModalName">Clinic Name</label>
                            <input type="text" class="form-control vd-input" id="clinicModalName" name="name" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label" for="clinicModalAddress">Address</label>
                            <input type="text" class="form-control vd-input" id="clinicModalAddress" name="address" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label" for="clinicModalEmbedUrl">Google Maps Embed URL <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control vd-input" id="clinicModalEmbedUrl" name="embed_url" rows="3" placeholder="Paste a Google Maps embed URL or full iframe"></textarea>
                            <div class="vd-field-help">In Google Maps, choose Share → Embed a map, then paste the URL or iframe code here.</div>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn vd-btn-gold" id="clinicModalSubmit">Add Clinic</button>
                </div>
            </form>
        </div>
    </div>
</div>

<div class="modal fade" id="clinicMapModal" tabindex="-1" aria-labelledby="clinicMapModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vd-modal-content">
            <div class="modal-header">
                <div>
                    <div class="vd-action-modal-kicker">Clinic location</div>
                    <h5 class="modal-title vd-modal-title mb-0" id="clinicMapModalTitle">View Map</h5>
                </div>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <div class="vd-clinic-map-frame">
                    <iframe id="clinicMapFrame" title="Clinic map" loading="lazy" referrerpolicy="no-referrer-when-downgrade" allowfullscreen></iframe>
                </div>
            </div>
            <div class="modal-footer">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Close</button>
            </div>
        </div>
    </div>
</div>

<div class="modal fade" id="deleteClinicModal" tabindex="-1" aria-labelledby="deleteClinicModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered">
        <div class="modal-content vd-modal-content vd-confirm-modal">
            <div class="modal-header border-0 pb-0">
                <h5 class="modal-title vd-modal-title" id="deleteClinicModalTitle">Delete Clinic</h5>
                <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
            </div>
            <div class="modal-body">
                <p class="mb-1" id="deleteClinicMessage"></p>
                <p class="mb-0 small text-muted">Clinics with schedules or appointments cannot be deleted.</p>
            </div>
            <div class="modal-footer border-0 pt-0">
                <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                <button type="button" class="btn vd-clinic-delete-confirm" id="deleteClinicConfirm">Delete</button>
            </div>
        </div>
    </div>
</div>

<script>
    (function() {
        const CONTROLLER = '../../../apps/controllers/clinicController.php';
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

        const clinicModalElement = document.getElementById('clinicModal');
        const clinicModal = bootstrap.Modal.getOrCreateInstance(clinicModalElement);
        const clinicForm = document.getElementById('clinicForm');
        const clinicIdInput = document.getElementById('clinicModalId');
        const clinicNameInput = document.getElementById('clinicModalName');
        const clinicAddressInput = document.getElementById('clinicModalAddress');
        const clinicEmbedInput = document.getElementById('clinicModalEmbedUrl');
        const clinicModalTitle = document.getElementById('clinicModalTitle');
        const clinicModalError = document.getElementById('clinicModalError');
        const clinicModalSubmit = document.getElementById('clinicModalSubmit');

        const mapModalElement = document.getElementById('clinicMapModal');
        const mapModal = bootstrap.Modal.getOrCreateInstance(mapModalElement);
        const mapModalTitle = document.getElementById('clinicMapModalTitle');
        const mapFrame = document.getElementById('clinicMapFrame');

        const deleteModalElement = document.getElementById('deleteClinicModal');
        const deleteModal = bootstrap.Modal.getOrCreateInstance(deleteModalElement);
        const deleteMessage = document.getElementById('deleteClinicMessage');
        const deleteConfirmButton = document.getElementById('deleteClinicConfirm');

        let initialFormState = '';
        let pendingDelete = null;

        function showToast(message, success) {
            if (typeof window.showToast === 'function') {
                window.showToast(message, success);
                return;
            }
            console.warn('showToast not available:', message);
        }

        function refreshClinics() {
            document.querySelector('[data-page="clinic-content.php"]')?.click();
        }

        function formState() {
            return JSON.stringify({
                name: clinicNameInput.value.trim(),
                address: clinicAddressInput.value.trim(),
                embedUrl: clinicEmbedInput.value.trim(),
            });
        }

        function updateSubmitState() {
            const editing = clinicIdInput.value !== '';
            clinicModalSubmit.disabled = editing && formState() === initialFormState;
        }

        function clearModalError() {
            clinicModalError.classList.add('d-none');
            clinicModalError.textContent = '';
        }

        function openClinicModal(row = null) {
            clinicForm.reset();
            clearModalError();

            const editing = Boolean(row);
            clinicIdInput.value = editing ? row.dataset.clinicId : '';
            clinicNameInput.value = editing ? row.dataset.name : '';
            clinicAddressInput.value = editing ? row.dataset.address : '';
            clinicEmbedInput.value = editing ? row.dataset.embedUrl : '';
            clinicModalTitle.textContent = editing ? 'Edit Clinic' : 'Add New Clinic';
            clinicModalSubmit.textContent = editing ? 'Save Changes' : 'Add Clinic';
            initialFormState = formState();
            updateSubmitState();
            clinicModal.show();
        }

        document.getElementById('addClinicBtn').addEventListener('click', () => openClinicModal());

        document.querySelectorAll('.vd-edit-clinic-btn').forEach(button => {
            button.addEventListener('click', function() {
                openClinicModal(this.closest('.vd-clinic-list-row'));
            });
        });

        document.querySelectorAll('.vd-view-clinic-map-btn:not(:disabled)').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('.vd-clinic-list-row');
                mapModalTitle.textContent = row.dataset.name;
                mapFrame.src = row.dataset.embedUrl;
                mapModal.show();
            });
        });

        mapModalElement.addEventListener('hidden.bs.modal', () => {
            mapFrame.removeAttribute('src');
        });

        document.querySelectorAll('.vd-delete-clinic-btn:not(:disabled)').forEach(button => {
            button.addEventListener('click', function() {
                const row = this.closest('.vd-clinic-list-row');
                pendingDelete = {
                    id: row.dataset.clinicId,
                    name: row.dataset.name,
                };
                deleteMessage.textContent = `Delete “${pendingDelete.name}”?`;
                deleteModal.show();
            });
        });

        clinicForm.addEventListener('input', updateSubmitState);

        clinicModalElement.addEventListener('hidden.bs.modal', () => {
            clinicForm.reset();
            clinicIdInput.value = '';
            initialFormState = '';
            clearModalError();
        });

        clinicForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            clearModalError();

            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            const editing = clinicIdInput.value !== '';
            const formData = new FormData(this);
            formData.append('action', editing ? 'updateInline' : 'add');
            formData.append('csrf_token', csrfToken);
            LoadingUI.setButton(clinicModalSubmit, true, editing ? 'Saving…' : 'Adding…');

            try {
                const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Failed to save clinic.');

                showToast(result.message || 'Clinic saved.', true);
                clinicModalElement.addEventListener('hidden.bs.modal', refreshClinics, { once: true });
                clinicModal.hide();
            } catch (error) {
                clinicModalError.textContent = error.message || 'Unable to save clinic.';
                clinicModalError.classList.remove('d-none');
                LoadingUI.setButton(clinicModalSubmit, false);
                updateSubmitState();
            }
        });

        deleteConfirmButton.addEventListener('click', async function() {
            if (!pendingDelete) return;

            const formData = new FormData();
            formData.append('action', 'delete');
            formData.append('clinic_id', pendingDelete.id);
            formData.append('csrf_token', csrfToken);
            LoadingUI.setButton(this, true, 'Deleting…');

            try {
                const response = await fetch(CONTROLLER, { method: 'POST', body: formData });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Failed to delete clinic.');

                showToast(result.message || 'Clinic deleted.', true);
                deleteModalElement.addEventListener('hidden.bs.modal', refreshClinics, { once: true });
                deleteModal.hide();
            } catch (error) {
                showToast(error.message || 'Unable to delete clinic.', false);
                LoadingUI.setButton(this, false);
            }
        });

        deleteModalElement.addEventListener('hidden.bs.modal', () => {
            pendingDelete = null;
            LoadingUI.setButton(deleteConfirmButton, false);
        });
    })();
</script>
