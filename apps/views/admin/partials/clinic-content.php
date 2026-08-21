<?php
if (session_status() === PHP_SESSION_NONE) session_start();

if (!isset($_SESSION['user_id']) || !in_array($_SESSION['user_role'], ['Admin', 'Dental Assistant'])) {
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

    <div class="d-flex flex-column flex-sm-row justify-content-between align-items-sm-center gap-3">
        <div>
            <div class="vd-welcome-greet">CLINIC MANAGEMENT</div>
            <div class="vd-welcome-name">Clinic Locations</div>
            <p class="text-muted small mb-0 mt-2">Add a clinic or update its address and map embed URL.</p>
        </div>
        <button type="button" class="btn vd-btn-gold align-self-start" data-bs-toggle="modal" data-bs-target="#addClinicModal">
            <i class="ti ti-plus me-1"></i> Add Clinic
        </button>
    </div>

    <?php if (empty($clinics)): ?>
        <div class="vd-empty-state">No clinics found.</div>
    <?php endif; ?>

    <?php foreach ($clinics as $clinic): ?>
        <div class="vd-dash-card" data-clinic-id="<?= $clinic['clinic_id'] ?>">
            <div class="vd-dash-card-header">
                <span class="vd-dash-card-title"><?= htmlspecialchars($clinic['clinic_name']) ?></span>
            </div>

            <div class="vd-dash-card-body">
                <div class="row g-3">
                        <div class="col-md-6">
                            <label class="vd-label form-label">Clinic Name</label>
                            <input type="text" class="form-control vd-input vd-clinic-field" data-field="name"
                                value="<?= htmlspecialchars($clinic['clinic_name']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label">Address</label>
                            <input type="text" class="form-control vd-input vd-clinic-field" data-field="address"
                                value="<?= htmlspecialchars($clinic['clinic_address']) ?>">
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label">Google Maps Embed URL</label>
                            <textarea class="form-control vd-input vd-clinic-field" data-field="embed_url" rows="2" placeholder="Paste Google Maps embed URL or full iframe"><?= htmlspecialchars($clinic['embed_url'] ?? '') ?></textarea>
                        </div>
                        <div class="col-12 d-flex justify-content-end">
                            <button class="btn vd-btn-gold btn-sm vd-save-clinic-btn" data-id="<?= $clinic['clinic_id'] ?>">
                                Save Changes
                            </button>
                        </div>
                </div>
            </div>
        </div>
    <?php endforeach; ?>

</div>

<div class="modal fade" id="addClinicModal" tabindex="-1" aria-labelledby="addClinicModalTitle" aria-hidden="true">
    <div class="modal-dialog modal-dialog-centered modal-lg">
        <div class="modal-content vd-modal-content">
            <form id="addClinicForm" novalidate>
                <div class="modal-header">
                    <div>
                        <div class="vd-action-modal-kicker">Clinic management</div>
                        <h5 class="modal-title vd-modal-title mb-0" id="addClinicModalTitle">Add New Clinic</h5>
                    </div>
                    <button type="button" class="btn-close" data-bs-dismiss="modal" aria-label="Close"></button>
                </div>
                <div class="modal-body">
                    <div id="addClinicError" class="alert alert-danger d-none" role="alert"></div>
                    <div class="row g-3">
                        <div class="col-md-6">
                            <label class="vd-label form-label" for="newClinicName">Clinic Name</label>
                            <input type="text" class="form-control vd-input" id="newClinicName" name="name" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label" for="newClinicAddress">Address</label>
                            <input type="text" class="form-control vd-input" id="newClinicAddress" name="address" maxlength="100" required>
                        </div>
                        <div class="col-12">
                            <label class="vd-label form-label" for="newClinicEmbedUrl">Google Maps Embed URL <span class="text-muted">(optional)</span></label>
                            <textarea class="form-control vd-input" id="newClinicEmbedUrl" name="embed_url" rows="2" placeholder="Paste Google Maps embed URL or full iframe"></textarea>
                        </div>
                    </div>
                </div>
                <div class="modal-footer">
                    <button type="button" class="btn vd-btn-outline" data-bs-dismiss="modal">Cancel</button>
                    <button type="submit" class="btn vd-btn-gold" id="addClinicSubmit">Add Clinic</button>
                </div>
            </form>
        </div>
    </div>
</div>

<script>
    (function() {
        const CONTROLLER = '../../../apps/controllers/clinicController.php';
        const csrfToken = <?= json_encode($_SESSION['csrf_token']) ?>;

        function showToast(msg, success) {
            if (typeof window.showToast === 'function') {
                window.showToast(msg, success);
                return;
            }
            console.warn('showToast not available:', msg);
        }

        document.querySelectorAll('.vd-save-clinic-btn').forEach(btn => {
            btn.addEventListener('click', async function() {
                const card = this.closest('.vd-dash-card');
                const id = this.dataset.id;
                const nameInput = card.querySelector('[data-field="name"]');
                const addressInput = card.querySelector('[data-field="address"]');
                const embedInput = card.querySelector('[data-field="embed_url"]');

                const formData = new FormData();
                formData.append('action', 'updateInline');
                formData.append('csrf_token', csrfToken);
                formData.append('clinic_id', id);
                formData.append('name', nameInput.value.trim());
                formData.append('address', addressInput.value.trim());
                formData.append('embed_url', embedInput.value.trim());

                const originalText = this.textContent;
                this.disabled = true;
                this.textContent = 'Saving…';

                LoadingUI.setButton(this, true, 'Saving…');
                try {
                    const response = await fetch(CONTROLLER, {
                        method: 'POST',
                        body: formData
                    });
                    const result = await response.json();

                    if (result.success) {
                        showToast(result.message || 'Clinic updated.', true);

                        // Keep the card header title in sync with the name field
                        const titleEl = card.querySelector('.vd-dash-card-title');
                        if (titleEl) titleEl.textContent = nameInput.value.trim();
                    } else {
                        showToast(result.message || 'Failed to update clinic.', false);
                    }
                } catch (err) {
                    showToast('Network error. Please try again.', false);
                    console.error(err);
                } finally {
                    LoadingUI.setButton(this, false);
                    this.disabled = false;
                    this.textContent = originalText;
                }
            });
        });

        const addClinicModalElement = document.getElementById('addClinicModal');
        const addClinicModal = bootstrap.Modal.getOrCreateInstance(addClinicModalElement);
        const addClinicForm = document.getElementById('addClinicForm');
        const addClinicError = document.getElementById('addClinicError');

        addClinicModalElement.addEventListener('hidden.bs.modal', () => {
            addClinicForm.reset();
            addClinicError.classList.add('d-none');
            addClinicError.textContent = '';
        });

        addClinicForm.addEventListener('submit', async function(event) {
            event.preventDefault();
            const submitButton = document.getElementById('addClinicSubmit');
            addClinicError.classList.add('d-none');

            if (!this.checkValidity()) {
                this.reportValidity();
                return;
            }

            const formData = new FormData(this);
            formData.append('action', 'add');
            formData.append('csrf_token', csrfToken);
            LoadingUI.setButton(submitButton, true, 'Adding…');

            try {
                const response = await fetch(CONTROLLER, {
                    method: 'POST',
                    body: formData
                });
                const result = await response.json();
                if (!result.success) throw new Error(result.message || 'Failed to add clinic.');

                showToast(result.message, true);
                addClinicModalElement.addEventListener('hidden.bs.modal', () => {
                    document.querySelector('[data-page="clinic-content.php"]')?.click();
                }, {
                    once: true
                });
                addClinicModal.hide();
            } catch (error) {
                addClinicError.textContent = error.message || 'Unable to add clinic.';
                addClinicError.classList.remove('d-none');
                LoadingUI.setButton(submitButton, false);
            }
        });
    })();
</script>